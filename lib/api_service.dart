import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';

const String defaultApiBase = 'https://3rah.ir/mm/api';
const String defaultApiToken = 'change_me_to_a_secure_token';

class MusicApi {
  MusicApi({this.baseUrl = defaultApiBase, this.token = defaultApiToken});

  final String baseUrl;
  final String token;

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
        'User-Agent': 'MusicMan-Flutter/1.0',
      };

  Future<dynamic> get(String path) async {
    final uri = Uri.parse('$baseUrl$path');
    final response = await http
        .get(uri, headers: _headers)
        .timeout(const Duration(seconds: 12));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('Server error: HTTP ${response.statusCode}');
    }
    return jsonDecode(response.body);
  }

  Future<List<MusicItem>> search(String term, {int limit = 50}) async {
    final clean = Uri.encodeQueryComponent(term.trim());
    final res = await get('/search?term=$clean&limit=$limit&entity=musicArtist,album,song');
    return _extractItems(res);
  }

  Future<List<SearchSuggestion>> suggest(String query, {int limit = 8}) async {
    if (query.trim().isEmpty) return [];
    final clean = Uri.encodeQueryComponent(query.trim());
    final res = await get('/suggest?q=$clean&limit=$limit');
    if (res is Map && res['suggestions'] is List) {
      return (res['suggestions'] as List)
          .whereType<Map>()
          .map((e) => SearchSuggestion.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    }
    return [];
  }

  Future<List<MusicItem>> fresh() async {
    final res = await get('/fresh');
    return _extractItems(res);
  }

  Future<List<MusicItem>> popular({int limit = 40}) async {
    final res = await get('/popular?limit=$limit&minViews=1');
    return _extractItems(res);
  }

  Future<List<MusicItem>> lookup(String id, String entity, {int limit = 200}) async {
    final cleanId = id.replaceAll(RegExp(r'^it_'), '');
    final res = await get('/lookup?id=${Uri.encodeQueryComponent(cleanId)}&entity=$entity&limit=$limit');
    return _extractItems(res);
  }

  Future<List<MusicItem>> getArtistTracks(
    String id, {
    int page = 1,
    int limit = 50,
    String sort = 'album',
  }) async {
    final cleanId = id.replaceAll(RegExp(r'^it_'), '');
    final res = await get(
        '/artist/tracks?id=${Uri.encodeQueryComponent(cleanId)}&page=$page&limit=$limit&sort=${Uri.encodeQueryComponent(sort)}');
    return _extractItems(res);
  }

  Future<void> addCrawlTask({
    required String trackId,
    String quality = '320',
    bool skipExisting = true,
  }) async {
    final cleanId = trackId.replaceAll(RegExp(r'^it_'), '');
    final uri = Uri.parse('$baseUrl/download/add');
    final response = await http
        .post(
          uri,
          headers: {..._headers, 'Content-Type': 'application/json'},
          body: jsonEncode({
            'trackId': cleanId,
            'quality': quality,
            'skipExisting': skipExisting,
          }),
        )
        .timeout(const Duration(seconds: 12));
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('Could not queue crawl: HTTP ${response.statusCode}');
    }
  }

  Future<CrawlStatus> getCrawlStatus(String trackId) async {
    final cleanId = trackId.replaceAll(RegExp(r'^it_'), '');
    try {
      final res = await get('/download/status?trackId=${Uri.encodeQueryComponent(cleanId)}');
      if (res is Map) {
        return CrawlStatus.fromJson(Map<String, dynamic>.from(res));
      }
    } catch (_) {}
    return CrawlStatus(downloadStatus: 'completed', percent: 100, found: false);
  }

  Future<Map<String, CrawlStatus>> getBatchStatus(List<String> ids) async {
    if (ids.isEmpty) return {};
    final cleanIds = ids.map((i) => i.replaceAll(RegExp(r'^it_'), '')).join(',');
    try {
      final res = await get('/download/batch-status?ids=$cleanIds');
      if (res is Map && res['items'] is Map) {
        final Map<String, dynamic> items = res['items'];
        return items.map((k, v) => MapEntry(
              k,
              CrawlStatus.fromJson(Map<String, dynamic>.from(v as Map)),
            ));
      }
    } catch (_) {}
    return {};
  }

  String proxyUrl(String targetUrl) {
    if (targetUrl.isEmpty) return '';
    return '$baseUrl/proxy?url=${Uri.encodeComponent(targetUrl)}';
  }

  static String telegramBotUrl(String type, String id) {
    final cleanId = id.replaceAll(RegExp(r'^it_'), '');
    return 'https://t.me/musicman_official_bot?start=${type}_$cleanId';
  }

  List<MusicItem> _extractItems(dynamic json) {
    if (json is Map && json['results'] is List) {
      return (json['results'] as List)
          .whereType<Map>()
          .map((e) => MusicItem(Map<String, dynamic>.from(e)))
          .toList();
    }
    return [];
  }
}
