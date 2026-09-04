using System.Windows;
using PulseAlarm.Windows.Services;

namespace PulseAlarm.Windows;

public partial class AccountWindow : Window
{
    private readonly PulseCloudClient _cloud;
    public bool SignedIn { get; private set; }

    public AccountWindow(PulseCloudClient cloud)
    {
        InitializeComponent();
        _cloud = cloud;
        EmailBox.Text = cloud.Email;
    }

    private async void SignIn_Click(object sender, RoutedEventArgs e)
    {
        var email = EmailBox.Text.Trim();
        var password = PasswordBox.Password;
        if (string.IsNullOrWhiteSpace(email) || string.IsNullOrWhiteSpace(password))
        {
            StatusText.Text = "Enter your Pulse Account email and password.";
            return;
        }
        try
        {
            SignInButton.IsEnabled = false;
            SignInButton.Content = "SIGNING IN...";
            StatusText.Text = "";
            await _cloud.SignInAsync(email, password);
            SignedIn = true;
            DialogResult = true;
        }
        catch (Exception ex)
        {
            StatusText.Text = ex.Message;
        }
        finally
        {
            SignInButton.IsEnabled = true;
            SignInButton.Content = "SIGN IN & SYNC";
        }
    }
}
