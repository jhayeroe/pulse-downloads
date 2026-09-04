using System.IO;
using System.Text.Json;
using PulseAlarm.Windows.Models;

namespace PulseAlarm.Windows.Services;

public sealed class AlarmStore
{
    private readonly string _path;
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    public AlarmStore()
    {
        var folder = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "PULSE Alarm");
        Directory.CreateDirectory(folder);
        _path = Path.Combine(folder, "alarms.json");
    }

    public List<AlarmItem> Load()
    {
        try
        {
            return File.Exists(_path)
                ? JsonSerializer.Deserialize<List<AlarmItem>>(File.ReadAllText(_path)) ?? []
                : [];
        }
        catch { return []; }
    }

    public void Save(IEnumerable<AlarmItem> alarms) =>
        File.WriteAllText(_path, JsonSerializer.Serialize(alarms, JsonOptions));
}
