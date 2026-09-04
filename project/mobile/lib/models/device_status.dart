class ActiveSession {
  final int id;
  final String name;
  final String type;
  final String startAt;
  final String endAt;

  ActiveSession({required this.id, required this.name, required this.type,
      required this.startAt, required this.endAt});

  factory ActiveSession.fromJson(Map<String, dynamic> j) => ActiveSession(
        id: j['id'] as int,
        name: j['name'] ?? '',
        type: j['type'] ?? '',
        startAt: j['start_at'] ?? '',
        endAt: j['end_at'] ?? '',
      );
}

class DeviceStatus {
  final String deviceStatus; // PENDING / ACTIVE / REVOKED
  final bool trackingRequired;
  final int trackingInterval; // detik
  final int standbyPollInterval; // detik
  final String serverTime;
  final String? personnelName;
  final String? nrp;
  final String? rank;
  final List<ActiveSession> activeSessions;
  final String? deviceToken;
  final String? message;

  DeviceStatus({
    required this.deviceStatus,
    this.trackingRequired = false,
    this.trackingInterval = 30,
    this.standbyPollInterval = 60,
    this.serverTime = '',
    this.personnelName,
    this.nrp,
    this.rank,
    this.activeSessions = const [],
    this.deviceToken,
    this.message,
  });

  factory DeviceStatus.fromJson(Map<String, dynamic> j) {
    final p = j['personnel'] as Map<String, dynamic>?;
    return DeviceStatus(
      deviceStatus: j['device_status'] ?? 'PENDING',
      trackingRequired: j['tracking_required'] == true,
      trackingInterval: j['tracking_interval'] ?? 30,
      standbyPollInterval: j['standby_poll_interval'] ?? 60,
      serverTime: j['server_time'] ?? '',
      personnelName: p?['name'],
      nrp: p?['nrp'],
      rank: p?['rank'],
      activeSessions: ((j['active_sessions'] as List?) ?? [])
          .map((e) => ActiveSession.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      deviceToken: j['device_token'],
      message: j['message'],
    );
  }
}
