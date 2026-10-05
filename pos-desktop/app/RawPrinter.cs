using System;
using System.Collections.Generic;
using System.Drawing.Printing;
using System.IO;
using System.Runtime.InteropServices;
using System.Text;

namespace AzolaPos
{
    /// <summary>Kirim byte mentah (ESC/POS) ke printer Windows lewat winspool, tanpa driver grafis.</summary>
    internal static class RawPrinter
    {
        public static void PrintReceipt(string printerName, IList<string> lines, bool openDrawer)
        {
            if (string.IsNullOrWhiteSpace(printerName)) printerName = new PrinterSettings().PrinterName;
            if (string.IsNullOrWhiteSpace(printerName)) throw new InvalidOperationException("Tidak ada printer. Atur nama printer di Pengaturan.");

            SendBytes(printerName, BuildEscPos(lines, openDrawer));
        }

        /// <summary>Buka laci saja (misalnya tombol "buka laci" nanti).</summary>
        public static void OpenDrawer(string printerName)
        {
            if (string.IsNullOrWhiteSpace(printerName)) printerName = new PrinterSettings().PrinterName;
            SendBytes(printerName, new byte[] { 0x1B, 0x70, 0x00, 0x19, 0xFA });
        }

        private static byte[] BuildEscPos(IList<string> lines, bool openDrawer)
        {
            using (var ms = new MemoryStream())
            {
                Write(ms, 0x1B, 0x40);                 // ESC @  : reset printer
                if (openDrawer) Write(ms, 0x1B, 0x70, 0x00, 0x19, 0xFA); // ESC p 0 : pulsa laci

                foreach (var line in lines)
                {
                    bool bold = line.StartsWith("TOTAL", StringComparison.Ordinal);
                    if (bold) Write(ms, 0x1B, 0x45, 0x01);   // tebal hidup
                    var bytes = Encoding.ASCII.GetBytes(ToAscii(line));
                    ms.Write(bytes, 0, bytes.Length);
                    ms.WriteByte(0x0A);
                    if (bold) Write(ms, 0x1B, 0x45, 0x00);   // tebal mati
                }

                Write(ms, 0x1B, 0x64, 0x04);           // ESC d 4 : maju 4 baris
                Write(ms, 0x1D, 0x56, 0x42, 0x00);     // GS V B 0 : potong sebagian
                return ms.ToArray();
            }
        }

        // Printer struk umumnya hanya ASCII; huruf aksen diganti agar tidak jadi karakter aneh.
        private static string ToAscii(string s)
        {
            var sb = new StringBuilder(s.Length);
            foreach (char c in s) sb.Append(c < 128 ? c : '?');
            return sb.ToString();
        }

        private static void Write(Stream s, params byte[] b) { s.Write(b, 0, b.Length); }

        // ---- winspool ----
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
        private class DOCINFOA
        {
            [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
            [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
            [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
        }

        [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true)]
        private static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);
        [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true)]
        private static extern bool ClosePrinter(IntPtr hPrinter);
        [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true)]
        private static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);
        [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true)]
        private static extern bool EndDocPrinter(IntPtr hPrinter);
        [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true)]
        private static extern bool StartPagePrinter(IntPtr hPrinter);
        [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true)]
        private static extern bool EndPagePrinter(IntPtr hPrinter);
        [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true)]
        private static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

        private static void SendBytes(string printerName, byte[] data)
        {
            IntPtr h;
            if (!OpenPrinter(printerName.Normalize(), out h, IntPtr.Zero))
                throw new InvalidOperationException("Printer \"" + printerName + "\" tidak ditemukan atau tidak bisa dibuka (kode " + Marshal.GetLastWin32Error() + ").");

            IntPtr unmanaged = Marshal.AllocCoTaskMem(data.Length);
            try
            {
                Marshal.Copy(data, 0, unmanaged, data.Length);
                var di = new DOCINFOA { pDocName = "Struk Azola Pos", pDataType = "RAW" };
                if (!StartDocPrinter(h, 1, di)) throw new InvalidOperationException("Printer menolak dokumen (kode " + Marshal.GetLastWin32Error() + ").");
                try
                {
                    StartPagePrinter(h);
                    int written;
                    if (!WritePrinter(h, unmanaged, data.Length, out written) || written != data.Length)
                        throw new InvalidOperationException("Gagal mengirim data ke printer (kode " + Marshal.GetLastWin32Error() + ").");
                    EndPagePrinter(h);
                }
                finally { EndDocPrinter(h); }
            }
            finally
            {
                Marshal.FreeCoTaskMem(unmanaged);
                ClosePrinter(h);
            }
        }
    }
}
