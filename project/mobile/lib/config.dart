// Konfigurasi API. Override saat build:
// flutter build apk --dart-define=API_BASE_URL=https://server-anda.example.com
class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000', // emulator Android -> localhost
  );
  static const String appVersion = '1.0.0';
}
