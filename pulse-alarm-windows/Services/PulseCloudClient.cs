using System.IO;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using PulseAlarm.Windows.Models;

namespace PulseAlarm.Windows.Services;

public sealed class PulseCloudClient
{
    private const string BaseUrl = "https://aviglyievilmbqxtjzsr.supabase.co";
    private const string ApiKey = "sb_publishable_2qfJSsd5jInntnakB01B5Q_3kudbcAa";
    private readonly HttpClient _http = new() { Timeout = TimeSpan.FromSeconds(12) };
    private readonly string _sessionPath;
    private SessionState _session = new();
    private int _syncing;

    public PulseCloudClient()
    {
        var folder = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "PULSE Alarm");
        Directory.CreateDirectory(folder);
        _sessionPath = Path.Combine(folder, "pulse-account.json");
        LoadSession();
    }

    public bool IsSignedIn => !string.IsNullOrWhiteSpace(_session.AccessToken);
    public string Email => _session.Email ?? "";
    public DateTimeOffset? LastSyncAt { get; private set; }
    public string LastError { get; private set; } = "";

    public async Task SignInAsync(string email, string password)
    {
        var payload = JsonSerializer.Serialize(new { email = email.Trim(), password });
        using var req = Request(HttpMethod.Post, "/auth/v1/token?grant_type=password", payload, null);
        using var res = await _http.SendAsync(req);
        var raw = await res.Content.ReadAsStringAsync();
        if (!res.IsSuccessStatusCode) throw new InvalidOperationException(ReadError(raw, "Sign in failed"));
        using var doc = JsonDocument.Parse(raw);
        var root = doc.RootElement;
        _session = new SessionState
        {
            Email = email.Trim(),
            AccessToken = root.TryGetProperty("access_token", out var at) ? at.GetString() ?? "" : "",
            RefreshToken = root.TryGetProperty("refresh_token", out var rt) ? rt.GetString() ?? "" : ""
        };
        if (string.IsNullOrWhiteSpace(_session.AccessToken)) throw new InvalidOperationException("Pulse Account did not return an access token.");
        SaveSession();
    }

    public void SignOut()
    {
        _session = new SessionState();
        LastSyncAt = null;
        LastError = "";
        try { if (File.Exists(_sessionPath)) File.Delete(_sessionPath); } catch { }
    }

    public async Task<List<AlarmItem>> SyncAsync(IEnumerable<AlarmItem> local)
    {
        if (!IsSignedIn) return local.ToList();
        if (Interlocked.Exchange(ref _syncing, 1) == 1) return local.ToList();
        try
        {
            var items = local.Select(ToCloudPayload).ToArray();
            var payload = JsonSerializer.Serialize(new { p_items = items });
            var raw = await SendAuthorizedAsync("/rest/v1/rpc/pulse_alarm_sync", payload);
            var merged = ParseCloudRows(raw, local);
            LastSyncAt = DateTimeOffset.Now;
            LastError = "";
            return merged;
        }
        catch (Exception ex)
        {
            LastError = ex.Message;
            throw;
        }
        finally { Interlocked.Exchange(ref _syncing, 0); }
    }

    private async Task<string> SendAuthorizedAsync(string path, string json)
    {
        using var req = Request(HttpMethod.Post, path, json, _session.AccessToken);
        using var res = await _http.SendAsync(req);
        var raw = await res.Content.ReadAsStringAsync();
        if (res.StatusCode == HttpStatusCode.Unauthorized && await RefreshAsync())
        {
            using var retry = Request(HttpMethod.Post, path, json, _session.AccessToken);
            using var retryRes = await _http.SendAsync(retry);
            var retryRaw = await retryRes.Content.ReadAsStringAsync();
            if (!retryRes.IsSuccessStatusCode) throw new InvalidOperationException(ReadError(retryRaw, "Cloud sync failed"));
            return retryRaw;
        }
        if (!res.IsSuccessStatusCode) throw new InvalidOperationException(ReadError(raw, "Cloud sync failed"));
        return raw;
    }

    private async Task<bool> RefreshAsync()
    {
        if (string.IsNullOrWhiteSpace(_session.RefreshToken)) return false;
        try
        {
            var payload = JsonSerializer.Serialize(new { refresh_token = _session.RefreshToken });
            using var req = Request(HttpMethod.Post, "/auth/v1/token?grant_type=refresh_token", payload, null);
            using var res = await _http.SendAsync(req);
            var raw = await res.Content.ReadAsStringAsync();
            if (!res.IsSuccessStatusCode) return false;
            using var doc = JsonDocument.Parse(raw);
            var root = doc.RootElement;
            if (!root.TryGetProperty("access_token", out var at) || string.IsNullOrWhiteSpace(at.GetString())) return false;
            _session.AccessToken = at.GetString() ?? "";
            if (root.TryGetProperty("refresh_token", out var rt) && !string.IsNullOrWhiteSpace(rt.GetString())) _session.RefreshToken = rt.GetString() ?? "";
            SaveSession();
            return true;
        }
        catch { return false; }
    }

    private HttpRequestMessage Request(HttpMethod method, string path, string json, string? token)
    {
        var req = new HttpRequestMessage(method, BaseUrl + path);
        req.Headers.TryAddWithoutValidation("apikey", ApiKey);
        if (!string.IsNullOrWhiteSpace(token)) req.Headers.Authorization = new AuthenticationHeaderValue("Bearer", token);
        req.Content = new StringContent(json, Encoding.UTF8, "application/json");
        return req;
    }

    private static object ToCloudPayload(AlarmItem a)
    {
        a.EnsureClientId();
        var due = new DateTimeOffset(a.DueAt).ToUniversalTime().ToString("O");
        return new
        {
            local_id = a.LocalId,
            client_id = a.CloudClientId,
            cloud_version = a.CloudVersion,
            title = a.Title,
            details = a.Details,
            scheduled_at = due,
            status = a.IsDone || !a.IsEnabled ? "done" : "active",
            enabled = a.IsEnabled && !a.IsDone,
            important = a.IsImportant,
            vibrate = a.Vibrate,
            bypass = a.BypassSilent,
            repeat_days = a.RepeatDays,
            base_hour = a.BaseHour == 0 && a.BaseMinute == 0 ? a.DueAt.Hour : a.BaseHour,
            base_minute = a.BaseHour == 0 && a.BaseMinute == 0 ? a.DueAt.Minute : a.BaseMinute,
            timezone = a.Timezone,
            category = a.Category,
            last_action = string.IsNullOrWhiteSpace(a.LastAction) ? "edit" : a.LastAction,
            snooze_minutes = a.SnoozeMinutes
        };
    }

    private static List<AlarmItem> ParseCloudRows(string raw, IEnumerable<AlarmItem> existing)
    {
        var oldByClient = existing.Where(x => !string.IsNullOrWhiteSpace(x.CloudClientId)).ToDictionary(x => x.CloudClientId, StringComparer.OrdinalIgnoreCase);
        var oldByLocal = existing.Where(x => x.LocalId != 0).GroupBy(x => x.LocalId).ToDictionary(g => g.Key, g => g.First());
        var result = new List<AlarmItem>();
        using var doc = JsonDocument.Parse(string.IsNullOrWhiteSpace(raw) ? "[]" : raw);
        if (doc.RootElement.ValueKind != JsonValueKind.Array) return existing.ToList();
        foreach (var r in doc.RootElement.EnumerateArray())
        {
            var status = GetString(r, "status", "active");
            if (status == "deleted" || (r.TryGetProperty("deleted_at", out var del) && del.ValueKind != JsonValueKind.Null)) continue;
            var client = GetString(r, "client_id", "");
            var localId = GetLong(r, "local_id", 0);
            AlarmItem a;
            if (!string.IsNullOrWhiteSpace(client) && oldByClient.TryGetValue(client, out var byClient)) a = byClient;
            else if (localId != 0 && oldByLocal.TryGetValue(localId, out var byLocal)) a = byLocal;
            else a = new AlarmItem();
            a.CloudClientId = client;
            a.CloudVersion = GetLong(r, "version", a.CloudVersion);
            a.LocalId = localId != 0 ? localId : a.LocalId;
            a.Title = GetString(r, "title", "Reminder");
            a.Details = GetString(r, "details", "");
            a.Category = GetString(r, "category", "General");
            a.IsImportant = GetBool(r, "important", false);
            a.Vibrate = GetBool(r, "vibrate", true);
            a.BypassSilent = GetBool(r, "bypass", true);
            a.IsEnabled = GetBool(r, "enabled", status == "active" || status == "snoozed");
            a.IsDone = status == "done" || !a.IsEnabled;
            a.LastAction = GetString(r, "last_action", "");
            a.SnoozeMinutes = (int)GetLong(r, "snooze_minutes", a.SnoozeMinutes);
            a.BaseHour = (int)GetLong(r, "base_hour", a.BaseHour);
            a.BaseMinute = (int)GetLong(r, "base_minute", a.BaseMinute);
            a.Timezone = GetString(r, "timezone", a.Timezone);
            if (r.TryGetProperty("repeat_days", out var days) && days.ValueKind == JsonValueKind.Array)
            {
                var list = days.EnumerateArray().Select(x => x.ValueKind == JsonValueKind.True).Take(7).ToList();
                while (list.Count < 7) list.Add(false);
                a.RepeatDays = list.ToArray();
            }
            if (r.TryGetProperty("scheduled_at", out var s) && DateTimeOffset.TryParse(s.GetString(), out var dto)) a.DueAt = dto.LocalDateTime;
            a.HasFired = a.IsDone || a.DueAt <= DateTime.Now;
            result.Add(a);
        }
        return result.OrderBy(x => x.DueAt).ToList();
    }

    private static string GetString(JsonElement e, string name, string fallback) => e.TryGetProperty(name, out var v) && v.ValueKind == JsonValueKind.String ? v.GetString() ?? fallback : fallback;
    private static long GetLong(JsonElement e, string name, long fallback)
    {
        if (!e.TryGetProperty(name, out var v) || v.ValueKind == JsonValueKind.Null) return fallback;
        if (v.ValueKind == JsonValueKind.Number && v.TryGetInt64(out var n)) return n;
        return long.TryParse(v.ToString(), out var parsed) ? parsed : fallback;
    }
    private static bool GetBool(JsonElement e, string name, bool fallback) => e.TryGetProperty(name, out var v) && (v.ValueKind == JsonValueKind.True || v.ValueKind == JsonValueKind.False) ? v.GetBoolean() : fallback;

    private void LoadSession()
    {
        try { if (File.Exists(_sessionPath)) _session = JsonSerializer.Deserialize<SessionState>(File.ReadAllText(_sessionPath)) ?? new SessionState(); } catch { _session = new SessionState(); }
    }
    private void SaveSession()
    {
        try { File.WriteAllText(_sessionPath, JsonSerializer.Serialize(_session)); } catch { }
    }
    private static string ReadError(string raw, string fallback)
    {
        try
        {
            using var d = JsonDocument.Parse(raw);
            foreach (var key in new[] { "msg", "message", "error_description", "error" })
                if (d.RootElement.TryGetProperty(key, out var v) && v.ValueKind == JsonValueKind.String && !string.IsNullOrWhiteSpace(v.GetString())) return v.GetString()!;
        }
        catch { }
        return fallback;
    }

    private sealed class SessionState
    {
        public string Email { get; set; } = "";
        public string AccessToken { get; set; } = "";
        public string RefreshToken { get; set; } = "";
    }
}
