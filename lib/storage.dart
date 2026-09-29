import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'models.dart';

class LocalDownloadItem {
  LocalDownloadItem({
    required this.item,
    required this.filePath,
    required this.fileSize,
    required this.downloadedAt,
  });

  final MusicItem item;
  final String filePath;
  final int fileSize;
  final int downloadedAt;

  Map<String, dynamic> toJson() => {
        'item': item.toJson(),
        'filePath': filePath,
        'fileSize': fileSize,
        'downloadedAt': downloadedAt,
      };

  factory LocalDownloadItem.fromJson(Map<String, dynamic> json) =>
      LocalDownloadItem(
        item: MusicItem.fromJson(Map<String, dynamic>.from(json['item'] ?? {})),
        filePath: '${json['filePath'] ?? ''}',
        fileSize: json['fileSize'] is num ? (json['fileSize'] as num).toInt() : 0,
        downloadedAt: json['downloadedAt'] is int
            ? json['downloadedAt']
            : DateTime.now().millisecondsSinceEpoch,
      );
}

class FollowedArtist {
  FollowedArtist({
    required this.artistId,
    required this.artistName,
    this.primaryGenreName = '',
    this.artwork = '',
    required this.followedAt,
  });

  final String artistId;
  final String artistName;
  final String primaryGenreName;
  final String artwork;
  final int followedAt;

  Map<String, dynamic> toJson() => {
        'artistId': artistId,
        'artistName': artistName,
        'primaryGenreName': primaryGenreName,
        'artwork': artwork,
        'followedAt': followedAt,
      };

  factory FollowedArtist.fromJson(Map<String, dynamic> json) => FollowedArtist(
        artistId: '${json['artistId'] ?? ''}',
        artistName: '${json['artistName'] ?? ''}',
        primaryGenreName: '${json['primaryGenreName'] ?? ''}',
        artwork: '${json['artwork'] ?? ''}',
        followedAt: json['followedAt'] is int
            ? json['followedAt']
            : DateTime.now().millisecondsSinceEpoch,
      );
}

class ListeningStats {
  ListeningStats({
    this.totalMs = 0,
    this.plays = 0,
    Map<String, TrackStat>? tracks,
    Map<String, int>? days,
  })  : tracks = tracks ?? {},
        days = days ?? {};

  int totalMs;
  int plays;
  Map<String, TrackStat> tracks;
  Map<String, int> days;

  Map<String, dynamic> toJson() => {
        'totalMs': totalMs,
        'plays': plays,
        'tracks': tracks.map((k, v) => MapEntry(k, v.toJson())),
        'days': days,
      };

  factory ListeningStats.fromJson(Map<String, dynamic> json) {
    final tr = (json['tracks'] as Map?) ?? {};
    final dy = (json['days'] as Map?) ?? {};
    return ListeningStats(
      totalMs: json['totalMs'] is int ? json['totalMs'] : 0,
      plays: json['plays'] is int ? json['plays'] : 0,
      tracks: tr.map((k, v) => MapEntry(
          '$k', TrackStat.fromJson(Map<String, dynamic>.from(v as Map)))),
      days: dy.map((k, v) => MapEntry('$k', v is num ? v.toInt() : 0)),
    );
  }
}

class TrackStat {
  TrackStat({
    required this.count,
    required this.ms,
    required this.name,
    required this.artist,
    required this.artistId,
    required this.artwork,
    required this.lastAt,
  });

  int count;
  int ms;
  String name;
  String artist;
  String artistId;
  String artwork;
  int lastAt;

  Map<String, dynamic> toJson() => {
        'count': count,
        'ms': ms,
        'name': name,
        'artist': artist,
        'artistId': artistId,
        'artwork': artwork,
        'lastAt': lastAt,
      };

  factory TrackStat.fromJson(Map<String, dynamic> json) => TrackStat(
        count: json['count'] is int ? json['count'] : 0,
        ms: json['ms'] is int ? json['ms'] : 0,
        name: '${json['name'] ?? ''}',
        artist: '${json['artist'] ?? ''}',
        artistId: '${json['artistId'] ?? ''}',
        artwork: '${json['artwork'] ?? ''}',
        lastAt: json['lastAt'] is int ? json['lastAt'] : 0,
      );
}

class AppStore extends ChangeNotifier {
  List<MusicItem> likes = [];
  List<MusicItem> history = [];
  List<FollowedArtist> following = [];
  List<LocalDownloadItem> downloads = [];
  List<Playlist> playlists = [];
  Map<String, String> notes = {};
  List<String> recentSearches = [];
  ListeningStats stats = ListeningStats();
  AppSettings settings = AppSettings();

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();

    likes = _readList(prefs, 'mm_likes')
        .map((x) => MusicItem.fromJson(x))
        .toList();
    history = _readList(prefs, 'mm_recently_played')
        .map((x) => MusicItem.fromJson(x))
        .toList();
    following = _readList(prefs, 'mm_followed')
        .map((x) => FollowedArtist.fromJson(x))
        .toList();
    downloads = _readList(prefs, 'mm_downloads_v2')
        .map((x) => LocalDownloadItem.fromJson(x))
        .toList();
    playlists = _readList(prefs, 'mm_playlists')
        .map((x) => Playlist.fromJson(x))
        .toList();
    recentSearches =
        (jsonDecode(prefs.getString('mm_recent') ?? '[]') as List)
            .whereType<String>()
            .toList();

    final rawNotes =
        jsonDecode(prefs.getString('mm_notes') ?? '{}') as Map<String, dynamic>;
    notes = rawNotes.map((k, v) => MapEntry(k, '$v'));

    final rawStats = jsonDecode(prefs.getString('mm_stats') ?? '{}');
    if (rawStats is Map) {
      stats = ListeningStats.fromJson(Map<String, dynamic>.from(rawStats));
    }

    final rawSet = jsonDecode(prefs.getString('mm_settings') ?? '{}');
    if (rawSet is Map) {
      settings = AppSettings.fromJson(Map<String, dynamic>.from(rawSet));
    }

    notifyListeners();
  }

  List<Map<String, dynamic>> _readList(SharedPreferences p, String key) {
    try {
      final raw = jsonDecode(p.getString(key) ?? '[]');
      if (raw is List) {
        return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
      }
    } catch (_) {}
    return [];
  }

  Future<void> _saveKey(String key, dynamic value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(key, jsonEncode(value));
    notifyListeners();
  }

  // Likes
  bool isLiked(String trackId) => likes.any((e) => e.trackId == trackId);

  void toggleLike(MusicItem item) {
    if (isLiked(item.trackId)) {
      likes.removeWhere((e) => e.trackId == item.trackId);
    } else {
      likes.insert(0, item);
    }
    _saveKey('mm_likes', likes.map((e) => e.toJson()).toList());
  }

  // History
  void recordPlayed(MusicItem item) {
    history.removeWhere((e) => e.trackId == item.trackId);
    history.insert(0, item);
    if (history.length > 50) history.removeLast();
    _saveKey('mm_recently_played', history.map((e) => e.toJson()).toList());
  }

  void clearHistory() {
    history.clear();
    _saveKey('mm_recently_played', []);
  }

  // Following
  bool isFollowing(String artistId) =>
      following.any((e) => e.artistId == artistId);

  void toggleFollow(
      String artistId, String artistName, String genre, String artwork) {
    if (isFollowing(artistId)) {
      following.removeWhere((e) => e.artistId == artistId);
    } else {
      following.insert(
        0,
        FollowedArtist(
          artistId: artistId,
          artistName: artistName,
          primaryGenreName: genre,
          artwork: artwork,
          followedAt: DateTime.now().millisecondsSinceEpoch,
        ),
      );
    }
    _saveKey('mm_followed', following.map((e) => e.toJson()).toList());
  }

  // Playlists
  Playlist? createPlaylist(String name) {
    if (name.trim().isEmpty) return null;
    final pl = Playlist(
      id: 'pl_${DateTime.now().millisecondsSinceEpoch}',
      name: name.trim(),
      tracks: [],
      createdAt: DateTime.now().millisecondsSinceEpoch,
    );
    playlists.add(pl);
    _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
    return pl;
  }

  void addToPlaylist(String playlistId, MusicItem item) {
    final pl = playlists.firstWhere((p) => p.id == playlistId, orElse: () => Playlist(id: '', name: '', tracks: [], createdAt: 0));
    if (pl.id.isNotEmpty && !pl.tracks.any((t) => t.trackId == item.trackId)) {
      pl.tracks.add(item);
      _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
    }
  }

  void removeFromPlaylist(String playlistId, String trackId) {
    final pl = playlists.firstWhere((p) => p.id == playlistId, orElse: () => Playlist(id: '', name: '', tracks: [], createdAt: 0));
    if (pl.id.isNotEmpty) {
      pl.tracks.removeWhere((t) => t.trackId == trackId);
      _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
    }
  }

  void deletePlaylist(String playlistId) {
    playlists.removeWhere((p) => p.id == playlistId);
    _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
  }

  void togglePlaylistPublic(String playlistId) {
    final pl = playlists.firstWhere((p) => p.id == playlistId, orElse: () => Playlist(id: '', name: '', tracks: [], createdAt: 0));
    if (pl.id.isNotEmpty) {
      pl.isPublic = !pl.isPublic;
      pl.updatedAt = DateTime.now().millisecondsSinceEpoch;
      _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
    }
  }

  // Notes
  String? getNote(String trackId) => notes[trackId];

  void setNote(String trackId, String? note) {
    if (note == null || note.trim().isEmpty) {
      notes.remove(trackId);
    } else {
      notes[trackId] = note.trim();
    }
    _saveKey('mm_notes', notes);
  }

  // Downloads / Caching
  bool isDownloaded(String trackId) =>
      downloads.any((d) => d.item.trackId == trackId && File(d.filePath).existsSync());

  String? getLocalFilePath(String trackId) {
    final found = downloads.firstWhere(
      (d) => d.item.trackId == trackId && File(d.filePath).existsSync(),
      orElse: () => LocalDownloadItem(
          item: MusicItem({}), filePath: '', fileSize: 0, downloadedAt: 0),
    );
    return found.filePath.isNotEmpty ? found.filePath : null;
  }

  Future<File> downloadAndSave(
      MusicItem item, void Function(double) onProgress) async {
    final url = item.playableUrl;
    if (url == null || url.isEmpty) {
      throw Exception('No playable audio URL available.');
    }
    final dir = await getApplicationDocumentsDirectory();
    final file = File('${dir.path}/track_${item.trackId}.mp3');

    await Dio().download(url, file.path, onReceiveProgress: (rec, total) {
      if (total > 0) onProgress(rec / total);
    });

    final size = await file.length();
    downloads.removeWhere((d) => d.item.trackId == item.trackId);
    downloads.insert(
      0,
      LocalDownloadItem(
        item: item,
        filePath: file.path,
        fileSize: size,
        downloadedAt: DateTime.now().millisecondsSinceEpoch,
      ),
    );
    await _saveKey('mm_downloads_v2', downloads.map((e) => e.toJson()).toList());
    return file;
  }

  Future<void> deleteDownload(String trackId) async {
    final idx = downloads.indexWhere((d) => d.item.trackId == trackId);
    if (idx >= 0) {
      final item = downloads[idx];
      try {
        final f = File(item.filePath);
        if (await f.exists()) await f.delete();
      } catch (_) {}
      downloads.removeAt(idx);
      await _saveKey('mm_downloads_v2', downloads.map((e) => e.toJson()).toList());
    }
  }

  Future<void> clearAllDownloads() async {
    for (final item in downloads) {
      try {
        final f = File(item.filePath);
        if (await f.exists()) await f.delete();
      } catch (_) {}
    }
    downloads.clear();
    await _saveKey('mm_downloads_v2', []);
  }

  // Stats
  void recordListeningTime(MusicItem item, int ms) {
    if (ms < 1000) return;
    stats.totalMs += ms;
    stats.plays += 1;

    final id = item.trackId;
    final stat = stats.tracks.putIfAbsent(
      id,
      () => TrackStat(
        count: 0,
        ms: 0,
        name: item.title,
        artist: item.artistName,
        artistId: item.artistId,
        artwork: item.artworkUrl,
        lastAt: 0,
      ),
    );

    stat.count += 1;
    stat.ms += ms;
    stat.lastAt = DateTime.now().millisecondsSinceEpoch;

    final dayKey = DateTime.now().toIso8601String().substring(0, 10);
    stats.days[dayKey] = (stats.days[dayKey] ?? 0) + ms;

    _saveKey('mm_stats', stats.toJson());
  }

  void resetStats() {
    stats = ListeningStats();
    _saveKey('mm_stats', stats.toJson());
  }

  // Recent searches
  void addRecentSearch(String term) {
    if (term.trim().isEmpty) return;
    recentSearches.removeWhere((s) => s.toLowerCase() == term.toLowerCase());
    recentSearches.insert(0, term.trim());
    if (recentSearches.length > 12) recentSearches = recentSearches.sublist(0, 12);
    _saveKey('mm_recent', recentSearches);
  }

  void clearRecentSearches() {
    recentSearches.clear();
    _saveKey('mm_recent', []);
  }

  // Settings
  void updateSettings(AppSettings newSettings) {
    settings = newSettings;
    _saveKey('mm_settings', settings.toJson());
  }

  // App Reset
  Future<void> resetAllData() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
    await clearAllDownloads();
    likes.clear();
    history.clear();
    following.clear();
    playlists.clear();
    notes.clear();
    recentSearches.clear();
    stats = ListeningStats();
    settings = AppSettings();
    notifyListeners();
  }

  // Backup JSON export/import
  String exportBackupJson() {
    final data = {
      '__mm_backup': 2,
      'exportedAt': DateTime.now().toIso8601String(),
      'likes': likes.map((e) => e.toJson()).toList(),
      'playlists': playlists.map((e) => e.toJson()).toList(),
      'following': following.map((e) => e.toJson()).toList(),
      'history': history.map((e) => e.toJson()).toList(),
      'stats': stats.toJson(),
      'settings': settings.toJson(),
      'notes': notes,
    };
    return jsonEncode(data);
  }

  Future<bool> importBackupJson(String jsonString) async {
    try {
      final Map<String, dynamic> data = jsonDecode(jsonString);
      if (data['__mm_backup'] == null) return false;

      if (data['likes'] is List) {
        likes = (data['likes'] as List)
            .whereType<Map>()
            .map((e) => MusicItem.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        _saveKey('mm_likes', likes.map((e) => e.toJson()).toList());
      }
      if (data['playlists'] is List) {
        playlists = (data['playlists'] as List)
            .whereType<Map>()
            .map((e) => Playlist.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        _saveKey('mm_playlists', playlists.map((e) => e.toJson()).toList());
      }
      if (data['following'] is List) {
        following = (data['following'] as List)
            .whereType<Map>()
            .map((e) => FollowedArtist.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        _saveKey('mm_followed', following.map((e) => e.toJson()).toList());
      }
      if (data['stats'] is Map) {
        stats = ListeningStats.fromJson(
            Map<String, dynamic>.from(data['stats'] as Map));
        _saveKey('mm_stats', stats.toJson());
      }
      if (data['settings'] is Map) {
        settings = AppSettings.fromJson(
            Map<String, dynamic>.from(data['settings'] as Map));
        _saveKey('mm_settings', settings.toJson());
      }
      if (data['notes'] is Map) {
        notes = (data['notes'] as Map).map((k, v) => MapEntry('$k', '$v'));
        _saveKey('mm_notes', notes);
      }
      notifyListeners();
      return true;
    } catch (_) {
      return false;
    }
  }
}
