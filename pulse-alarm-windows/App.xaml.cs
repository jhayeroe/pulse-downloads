using System.Windows;

namespace PulseAlarm.Windows;

public partial class App : System.Windows.Application
{
    private MainWindow? _main;

    protected override void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);
        _main = new MainWindow();
        _main.Show();
    }
}
