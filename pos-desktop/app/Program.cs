using System;
using System.Threading;
using System.Windows.Forms;

namespace AzolaPos
{
    internal static class Program
    {
        [STAThread]
        private static void Main()
        {
            // Satu kasir = satu jendela. Membuka aplikasi dua kali hanya memunculkan yang sudah ada.
            bool created;
            using (var mutex = new Mutex(true, "AzolaPos.SingleInstance", out created))
            {
                if (!created)
                {
                    MessageBox.Show("Azola Pos sudah berjalan.", "Azola Pos", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    return;
                }

                Application.EnableVisualStyles();
                Application.SetCompatibleTextRenderingDefault(false);
                Application.Run(new MainForm());
            }
        }
    }
}
