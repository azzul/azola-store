using System;
using System.Collections;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace AzolaPos
{
    /// <summary>
    /// Cangkang tipis: menampilkan tampilan kasir (folder ui) di WebView2 dan menyediakan dua hal
    /// yang tidak bisa dilakukan halaman web: cetak struk ESC/POS langsung ke printer dan buka laci kasir.
    /// Semua logika penjualan ada di ui/pos.js dan server.
    /// </summary>
    public class MainForm : Form
    {
        private const string Host = "pos.local";
        private readonly WebView2 web = new WebView2 { Dock = DockStyle.Fill };
        private readonly JavaScriptSerializer json = new JavaScriptSerializer { MaxJsonLength = int.MaxValue };

        public MainForm()
        {
            Text = "Azola Pos";
            MinimumSize = new System.Drawing.Size(360, 560);
            Size = new System.Drawing.Size(1280, 800);
            StartPosition = FormStartPosition.CenterScreen;
            Controls.Add(web);
            Load += async (s, e) => await InitAsync();
        }

        private async Task InitAsync()
        {
            try
            {
                // Data lokal (produk, antrean transaksi) disimpan di folder pengguna, bukan di folder aplikasi.
                string data = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "AzolaPos", "webview");
                Directory.CreateDirectory(data);

                // Server di jaringan lokal biasanya http:// sedangkan halaman kasir berjalan di https://pos.local.
                var options = new CoreWebView2EnvironmentOptions("--allow-running-insecure-content");
                var env = await CoreWebView2Environment.CreateAsync(null, data, options);
                await web.EnsureCoreWebView2Async(env);

                var core = web.CoreWebView2;
                bool dev = Debugger.IsAttached || Environment.GetEnvironmentVariable("AZP_DEV") == "1";
                core.Settings.AreDevToolsEnabled = dev;
                core.Settings.AreDefaultContextMenusEnabled = dev;
                core.Settings.IsStatusBarEnabled = false;
                core.Settings.IsZoomControlEnabled = false;

                string ui = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "ui");
                if (!File.Exists(Path.Combine(ui, "index.html")))
                {
                    MessageBox.Show("Folder ui tidak ditemukan di samping Azola Pos.exe.\n" + ui, "Azola Pos", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    Close();
                    return;
                }
                core.SetVirtualHostNameToFolderMapping(Host, ui, CoreWebView2HostResourceAccessKind.Allow);

                // Kasir tidak boleh "tersesat" ke situs lain.
                core.NavigationStarting += (s, e) =>
                {
                    if (!e.Uri.StartsWith("https://" + Host + "/", StringComparison.OrdinalIgnoreCase)) e.Cancel = true;
                };
                core.NewWindowRequested += (s, e) => e.Handled = true;
                core.WebMessageReceived += OnMessage;

                core.Navigate("https://" + Host + "/index.html");
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "Azola Pos tidak bisa menyalakan mesin tampilan (Microsoft Edge WebView2).\n\n" +
                    "Pasang \"WebView2 Runtime\" dari microsoft.com/edge/webview2 lalu buka lagi.\n\n" + ex.Message,
                    "Azola Pos", MessageBoxButtons.OK, MessageBoxIcon.Error);
                Close();
            }
        }

        private void OnMessage(object sender, CoreWebView2WebMessageReceivedEventArgs e)
        {
            Dictionary<string, object> msg;
            try { msg = json.Deserialize<Dictionary<string, object>>(e.WebMessageAsJson); }
            catch { return; }

            object type;
            if (!msg.TryGetValue("type", out type) || (type as string) != "print") return;

            string printer = msg.ContainsKey("printer") ? msg["printer"] as string : null;
            bool drawer = msg.ContainsKey("drawer") && msg["drawer"] is bool && (bool)msg["drawer"];
            var lines = new List<string>();
            var raw = msg.ContainsKey("lines") ? msg["lines"] as IEnumerable : null;
            if (raw != null) foreach (var l in raw) lines.Add(Convert.ToString(l));

            // Cetak di thread lain supaya tampilan tidak membeku saat printer lambat.
            Task.Run(() =>
            {
                string error = null;
                try { RawPrinter.PrintReceipt(printer, lines, drawer); }
                catch (Exception ex) { error = ex.Message; }

                BeginInvoke((Action)(() =>
                {
                    if (web.CoreWebView2 == null) return;
                    var reply = new Dictionary<string, object> { { "type", "print-result" }, { "ok", error == null }, { "error", error } };
                    web.CoreWebView2.PostWebMessageAsJson(json.Serialize(reply));
                }));
            });
        }
    }
}
