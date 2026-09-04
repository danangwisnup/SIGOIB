import 'package:flutter/material.dart';
import 'screens/activation_screen.dart';
import 'screens/home_screen.dart';
import 'services/background_service.dart';
import 'services/storage_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await StorageService.instance.init();
  // Background service hanya dijalankan setelah device ACTIVE (punya token),
  // agar tidak ada komunikasi sebelum admin approve.
  final token = await StorageService.instance.deviceToken;
  if (token != null) {
    await initBackgroundService();
  }
  runApp(const MonitoringApp());
}

class MonitoringApp extends StatelessWidget {
  const MonitoringApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Monitoring IB',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1E293B)),
        useMaterial3: true,
      ),
      home: const _EntryPoint(),
    );
  }
}

class _EntryPoint extends StatefulWidget {
  const _EntryPoint();
  @override
  State<_EntryPoint> createState() => _EntryPointState();
}

class _EntryPointState extends State<_EntryPoint> {
  String? _token;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    StorageService.instance.deviceToken.then((t) => setState(() {
          _token = t;
          _loaded = true;
        }));
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_token == null) return const ActivationScreen();
    return const HomeScreen();
  }
}
