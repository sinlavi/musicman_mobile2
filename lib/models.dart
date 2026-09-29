class MusicItem {
  MusicItem(this.raw);

  final Map<String, dynamic> raw;

  String get id {
    final tid = raw['trackId'] ?? raw['collectionId'] ?? raw['artistId'];
    return tid != null ? '$tid' : '';
  }

  String get trackId => '${raw['trackId'] ?? ''}';
  String get collectionId => '${raw['collectionId'] ?? ''}';
  String get artistId => '${raw['artistId'] ?? ''}';

  String get title =>
      '${raw['trackName'] ?? raw['collectionName'] ?? raw['artistName'] ?? 'Unknown'}';
  String get trackName => '${raw['trackName'] ?? ''}';
  String get collectionName => '${raw['collectionName'] ?? ''}';
  String get artistName => '${raw['artistName'] ?? ''}';

  String get type {
    final w = '${raw['wrapperType'] ?? raw['kind'] ?? raw['type'] ?? ''}'.toLowerCase();
    if (w.contains('artist')) return 'artist';
    if (w.contains('collection') || w.contains('album')) return 'collection';
    return 'track';
  }

  String get artworkUrl {
    final a = raw['attachments'];
    if (a is Map) {
      final urls = a['artworkUrls'];
      if (urls is List && urls.isNotEmpty) {
        final last = urls.last;
        if (last is Map && last['url'] != null) {
          return '${last['url']}'.replaceAll(RegExp(r'/\d+x\d+(bb)?\.'), '/600x600bb.');
        }
      }
    }
    final art = raw['artworkUrl100'] ?? raw['artworkUrl'] ?? '';
    return art.toString().replaceAll(RegExp(r'/\d+x\d+(bb)?\.'), '/600x600bb.');
  }

  bool get hasAudio {
    final a = raw['attachments'];
    if (a is Map) {
      final audio = a['audioUrls'];
      if (audio is List && audio.isNotEmpty) {
        return audio.any((e) => e is Map && e['url'] != null);
      }
    }
    return false;
  }

  bool get hasPreview {
    final a = raw['attachments'];
    if (a is Map) {
      final prev = a['previewUrls'];
      if (prev is List && prev.isNotEmpty) {
        return prev.any((e) => e is Map && e['url'] != null);
      }
    }
    return raw['previewUrl'] != null;
  }

  String? get previewUrl {
    final a = raw['attachments'];
    if (a is Map) {
      final prev = a['previewUrls'];
      if (prev is List && prev.isNotEmpty) {
        for (final item in prev) {
          if (item is Map && item['url'] != null) return '${item['url']}';
        }
      }
    }
    return raw['previewUrl']?.toString();
  }

  String? get playableUrl {
    final a = raw['attachments'];
    if (a is Map) {
      final audio = a['audioUrls'];
      if (audio is List && audio.isNotEmpty) {
        for (final item in audio) {
          if (item is Map && item['url'] != null) return '${item['url']}';
        }
      }
    }
    return previewUrl;
  }

  Duration get duration => Duration(
      milliseconds: raw['trackTimeMillis'] is num
          ? (raw['trackTimeMillis'] as num).toInt()
          : 0);

  String get primaryGenreName => '${raw['primaryGenreName'] ?? ''}';
  String get releaseDate => '${raw['releaseDate'] ?? ''}';
  int get trackNumber =>
      raw['trackNumber'] is num ? (raw['trackNumber'] as num).toInt() : 0;
  int get trackCount =>
      raw['trackCount'] is num ? (raw['trackCount'] as num).toInt() : 0;

  dynamic get rawLyrics => raw['lyrics'] ?? (raw['attachments'] is Map ? raw['attachments']['lyrics'] : null);

  Map<String, dynamic> toJson() => raw;

  factory MusicItem.fromJson(Map<String, dynamic> json) => MusicItem(json);
}

class LyricLine {
  LyricLine({this.time, required this.text});
  final double? time; // in seconds
  final String text;
}

class TrackLyrics {
  TrackLyrics({required this.lines, required this.isSynced});
  final List<LyricLine> lines;
  final bool isSynced;

  factory TrackLyrics.parse(dynamic raw) {
    if (raw == null) return TrackLyrics(lines: [], isSynced: false);
    List<LyricLine> lines = [];
    String rawText = '';

    void ingest(dynamic node, int depth) {
      if (node == null || depth > 6) return;
      if (node is String) {
        if (rawText.isEmpty) rawText = node;
        return;
      }
      if (node is List) {
        if (lines.isEmpty) {
          lines = node.map((l) {
            if (l is String) return LyricLine(text: l);
            if (l is Map) {
              final t = l['time'] ?? l['startTime'] ?? l['start'] ?? l['timestamp'] ?? l['at'] ?? l['t'] ?? l['offset'];
              final txt = l['text'] ?? l['line'] ?? l['content'] ?? l['lyric'] ?? l['l'] ?? l['value'] ?? '';
              return LyricLine(
                  time: t is num ? t.toDouble() : null, text: '$txt');
            }
            return LyricLine(text: '$l');
          }).toList();
        }
        return;
      }
      if (node is Map) {
        if (node['text'] is Map) ingest(node['text'], depth + 1);
        if (node['synced'] is String && (node['synced'] as String).trim().isNotEmpty) {
          if (rawText.isEmpty) rawText = node['synced'];
          return;
        }
        if (node['synced'] is List && (node['synced'] as List).isNotEmpty) {
          ingest(node['synced'], depth + 1);
          return;
        }
        if (node['lines'] is List && (node['lines'] as List).isNotEmpty) {
          ingest(node['lines'], depth + 1);
          return;
        }
        if (node['plain'] is String) { rawText = node['plain']; return; }
        if (node['lyrics'] is String) { rawText = node['lyrics']; return; }
        if (node['text'] is String) { rawText = node['text']; return; }
      }
    }

    ingest(raw, 0);

    if (lines.isEmpty && rawText.isNotEmpty) {
      final re = RegExp(r'\[(\d{1,2}):(\d{1,2})(?:[.:](\d{1,3}))?\]');
      final list = <LyricLine>[];
      for (final line in rawText.split('\n')) {
        final matches = re.allMatches(line);
        if (matches.isNotEmpty) {
          final cleanText = line.replaceAll(re, '').trim();
          for (final m in matches) {
            final min = int.parse(m.group(1)!);
            final sec = int.parse(m.group(2)!);
            final ms = m.group(3) != null
                ? int.parse(m.group(3)!.padRight(3, '0').substring(0, 3)) / 1000.0
                : 0.0;
            list.add(LyricLine(time: min * 60 + sec + ms, text: cleanText));
          }
        } else {
          list.add(LyricLine(text: line.trim()));
        }
      }
      lines = list;
    }

    final isSynced = lines.any((l) => l.time != null);
    return TrackLyrics(lines: lines, isSynced: isSynced);
  }
}

class CrawlStatus {
  CrawlStatus({
    required this.downloadStatus,
    required this.percent,
    required this.found,
    this.error,
  });

  final String downloadStatus; // completed, pending, downloading, failed, stopped
  final double percent;
  final bool found;
  final String? error;

  factory CrawlStatus.fromJson(Map<String, dynamic> json) {
    final d = json['download'] ?? json;
    final status = '${d['download_status'] ?? d['status'] ?? 'completed'}';
    final pct = (d['percent'] is num) ? (d['percent'] as num).toDouble() : 100.0;
    return CrawlStatus(
      downloadStatus: status,
      percent: pct,
      found: json['found'] != false,
      error: d['error']?.toString(),
    );
  }
}

class SearchSuggestion {
  SearchSuggestion({required this.id, required this.name, required this.type});
  final String id;
  final String name;
  final String type;

  factory SearchSuggestion.fromJson(Map<String, dynamic> json) => SearchSuggestion(
        id: '${json['id'] ?? ''}',
        name: '${json['name'] ?? ''}',
        type: '${json['type'] ?? 'track'}',
      );
}

class Playlist {
  Playlist({
    required this.id,
    required this.name,
    required this.tracks,
    required this.createdAt,
    this.isPublic = false,
    this.updatedAt,
  });

  final String id;
  String name;
  final List<MusicItem> tracks;
  final int createdAt;
  bool isPublic;
  int? updatedAt;

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'tracks': tracks.map((t) => t.toJson()).toList(),
        'createdAt': createdAt,
        'isPublic': isPublic,
        'updatedAt': updatedAt,
      };

  factory Playlist.fromJson(Map<String, dynamic> json) => Playlist(
        id: '${json['id']}',
        name: '${json['name'] ?? 'Playlist'}',
        tracks: ((json['tracks'] as List?) ?? [])
            .whereType<Map>()
            .map((x) => MusicItem.fromJson(Map<String, dynamic>.from(x)))
            .toList(),
        createdAt: json['createdAt'] is int ? json['createdAt'] : DateTime.now().millisecondsSinceEpoch,
        isPublic: json['isPublic'] == true,
        updatedAt: json['updatedAt'] is int ? json['updatedAt'] : null,
      );
}

class AppSettings {
  AppSettings({
    this.theme = 'dark',
    this.autoTheme = false,
    this.appBg = false,
    this.animations = true,
    this.crossfadeMs = 0,
    this.sleepFade = true,
    this.autoScrollLyrics = true,
    this.autoRetry = true,
    this.autoSaveAfterCrawl = false,
    this.autoSavePlayed = false,
    this.saveData = false,
    this.accent = 'auto',
    this.lyricSize = 1.0,
  });

  String theme;
  bool autoTheme;
  bool appBg;
  bool animations;
  int crossfadeMs;
  bool sleepFade;
  bool autoScrollLyrics;
  bool autoRetry;
  bool autoSaveAfterCrawl;
  bool autoSavePlayed;
  bool saveData;
  String accent;
  double lyricSize;

  Map<String, dynamic> toJson() => {
        'theme': theme,
        'autoTheme': autoTheme,
        'appBg': appBg,
        'animations': animations,
        'crossfadeMs': crossfadeMs,
        'sleepFade': sleepFade,
        'autoScrollLyrics': autoScrollLyrics,
        'autoRetry': autoRetry,
        'autoSaveAfterCrawl': autoSaveAfterCrawl,
        'autoSavePlayed': autoSavePlayed,
        'saveData': saveData,
        'accent': accent,
        'lyricSize': lyricSize,
      };

  factory AppSettings.fromJson(Map<String, dynamic> json) => AppSettings(
        theme: '${json['theme'] ?? 'dark'}',
        autoTheme: json['autoTheme'] == true,
        appBg: json['appBg'] == true,
        animations: json['animations'] != false,
        crossfadeMs: json['crossfadeMs'] is int ? json['crossfadeMs'] : 0,
        sleepFade: json['sleepFade'] != false,
        autoScrollLyrics: json['autoScrollLyrics'] != false,
        autoRetry: json['autoRetry'] != false,
        autoSaveAfterCrawl: json['autoSaveAfterCrawl'] == true,
        autoSavePlayed: json['autoSavePlayed'] == true,
        saveData: json['saveData'] == true,
        accent: '${json['accent'] ?? 'auto'}',
        lyricSize: json['lyricSize'] is num ? (json['lyricSize'] as num).toDouble() : 1.0,
      );
}
