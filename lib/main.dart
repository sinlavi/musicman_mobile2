import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:audio_session/audio_session.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

const apiBase = 'https://3rah.ir/mm/api';
const apiToken = 'change_me_to_a_secure_token';

void main() => runApp(const MusicManApp());

class MusicManApp extends StatefulWidget {
  const MusicManApp({super.key});
  @override
  State<MusicManApp> createState() => _MusicManAppState();
}

class _MusicManAppState extends State<MusicManApp> {
  final library = LibraryStore();
  final player = PlayerController();
  bool dark = true;

  @override
  void initState() {
    super.initState();
    library.load().then((_) => setState(() {}));
    player.init();
  }

  @override
  void dispose() { player.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'MusicMan', debugShowCheckedModeBanner: false,
    theme: ThemeData(colorSchemeSeed: const Color(0xff7b2ff7), brightness: dark ? Brightness.dark : Brightness.light, useMaterial3: true),
    home: HomeShell(library: library, player: player, dark: dark, onTheme: () => setState(() => dark = !dark)),
  );
}

class MusicApi {
  static Future<dynamic> get(String path) async {
    final response = await http.get(Uri.parse('$apiBase$path'), headers: {'Accept': 'application/json', 'Authorization': 'Bearer $apiToken'}).timeout(const Duration(seconds: 12));
    if (response.statusCode < 200 || response.statusCode >= 300) throw Exception('Server returned ${response.statusCode}');
    return jsonDecode(response.body);
  }
  static Future<List<MusicItem>> search(String term) async => _items(await get('/search?term=${Uri.encodeQueryComponent(term)}&limit=50&entity=musicArtist,album,song'));
  static Future<List<MusicItem>> fresh() async => _items(await get('/fresh'));
  static Future<List<MusicItem>> popular() async => _items(await get('/popular?limit=40&minViews=1'));
  static Future<List<MusicItem>> lookup(String id, String entity) async => _items(await get('/lookup?id=${Uri.encodeQueryComponent(id)}&entity=$entity&limit=200'));
  static Future<void> crawl(String trackId) async {
    final r = await http.post(Uri.parse('$apiBase/download/add'), headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer $apiToken'}, body: jsonEncode({'trackId': trackId, 'quality': '320', 'skipExisting': true})).timeout(const Duration(seconds: 12));
    if (r.statusCode < 200 || r.statusCode >= 300) throw Exception('Could not queue crawl (${r.statusCode})');
  }
  static List<MusicItem> _items(dynamic v) => ((v is Map ? v['results'] : null) as List? ?? []).whereType<Map>().map((e) => MusicItem(Map<String, dynamic>.from(e))).toList();
}

class MusicItem {
  MusicItem(this.raw);
  final Map<String, dynamic> raw;
  String get id => '${raw['trackId'] ?? raw['collectionId'] ?? raw['artistId'] ?? ''}';
  String get title => '${raw['trackName'] ?? raw['collectionName'] ?? raw['artistName'] ?? 'Unknown'}';
  String get artist => '${raw['artistName'] ?? ''}';
  String get album => '${raw['collectionName'] ?? ''}';
  String get type { final w = '${raw['wrapperType'] ?? raw['kind'] ?? ''}'.toLowerCase(); return w.contains('artist') ? 'artist' : (w.contains('collection') || w.contains('album') ? 'album' : 'track'); }
  String get artwork { final a = raw['attachments']; final list = a is Map ? a['artworkUrls'] : null; final u = list is List && list.isNotEmpty && list.last is Map ? list.last['url'] : raw['artworkUrl100']; return '${u ?? ''}'.replaceAll(RegExp(r'/\d+x\d+(bb)?\.'), '/600x600bb.'); }
  String? get playable { final a = raw['attachments']; for (final list in [a is Map ? a['audioUrls'] : null, a is Map ? a['previewUrls'] : null]) { if (list is List && list.isNotEmpty && list.first is Map && list.first['url'] != null) return '${list.first['url']}'; } return raw['previewUrl']?.toString(); }
  Duration get duration => Duration(milliseconds: raw['trackTimeMillis'] is num ? (raw['trackTimeMillis'] as num).toInt() : 0);
  Map<String, dynamic> toJson() => raw;
  static MusicItem fromJson(Map<String, dynamic> j) => MusicItem(j);
}

class LibraryStore extends ChangeNotifier {
  List<MusicItem> likes = [], history = [], downloads = [];
  Map<String, List<MusicItem>> playlists = {};
  Future<void> load() async { final p = await SharedPreferences.getInstance(); likes = _read(p, 'likes'); history = _read(p, 'history'); downloads = _read(p, 'downloads'); final raw = jsonDecode(p.getString('playlists') ?? '{}') as Map<String, dynamic>; playlists = raw.map((k, v) => MapEntry(k, (v as List).whereType<Map>().map((x) => MusicItem.fromJson(Map<String, dynamic>.from(x))).toList())); notifyListeners(); }
  List<MusicItem> _read(SharedPreferences p, String k) => ((jsonDecode(p.getString(k) ?? '[]')) as List).whereType<Map>().map((x) => MusicItem.fromJson(Map<String, dynamic>.from(x))).toList();
  Future<void> _save() async { final p = await SharedPreferences.getInstance(); await p.setString('likes', jsonEncode(likes.map((e) => e.toJson()).toList())); await p.setString('history', jsonEncode(history.map((e) => e.toJson()).toList())); await p.setString('downloads', jsonEncode(downloads.map((e) => e.toJson()).toList())); await p.setString('playlists', jsonEncode(playlists.map((k,v) => MapEntry(k, v.map((e) => e.toJson()).toList())))); notifyListeners(); }
  bool liked(MusicItem item) => likes.any((e) => e.id == item.id);
  void toggleLike(MusicItem item) { liked(item) ? likes.removeWhere((e) => e.id == item.id) : likes.insert(0, item); _save(); }
  void played(MusicItem item) { history.removeWhere((e) => e.id == item.id); history.insert(0, item); if (history.length > 50) history.removeLast(); _save(); }
  void addToPlaylist(String name, MusicItem item) { final list = playlists.putIfAbsent(name, () => []); if (!list.any((e) => e.id == item.id)) list.add(item); _save(); }
  void createPlaylist(String name) { if (name.trim().isNotEmpty) { playlists.putIfAbsent(name.trim(), () => []); _save(); } }
  Future<File> cache(MusicItem item, void Function(double) progress) async { final url = item.playable; if (url == null) throw Exception('No audio is available yet. Use Crawl from the track page.'); final dir = await getApplicationDocumentsDirectory(); final file = File('${dir.path}/${item.id}.mp3'); await Dio().download(url, file.path, onReceiveProgress: (r, t) { if (t > 0) progress(r / t); }); downloads.removeWhere((e) => e.id == item.id); downloads.add(item); await _save(); return file; }
}

class PlayerController extends ChangeNotifier {
  final audio = AudioPlayer(); MusicItem? current; List<MusicItem> queue = []; int index = 0;
  Future<void> init() async { final s = await AudioSession.instance; await s.configure(const AudioSessionConfiguration.music()); audio.playerStateStream.listen((_) => notifyListeners()); audio.positionStream.listen((_) => notifyListeners()); audio.processingStateStream.listen((s) { if (s == ProcessingState.completed) next(); }); }
  bool get playing => audio.playing;
  Future<void> play(MusicItem item, {List<MusicItem>? items}) async { final url = item.playable; if (url == null) throw Exception('This song is not ready to play yet.'); queue = items ?? [item]; index = queue.indexWhere((e) => e.id == item.id); if (index < 0) index = 0; current = item; await audio.setUrl(url); await audio.play(); notifyListeners(); }
  Future<void> toggle() async => playing ? audio.pause() : audio.play();
  Future<void> next() async { if (queue.isEmpty) return; index = (index + 1) % queue.length; await play(queue[index], items: queue); }
  Future<void> previous() async { if (queue.isEmpty) return; index = (index - 1 + queue.length) % queue.length; await play(queue[index], items: queue); }
  void dispose() => audio.dispose();
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.library, required this.player, required this.dark, required this.onTheme});
  final LibraryStore library; final PlayerController player; final bool dark; final VoidCallback onTheme;
  @override State<HomeShell> createState() => _HomeShellState();
}
class _HomeShellState extends State<HomeShell> {
  int tab = 0;
  void open(MusicItem item, {List<MusicItem>? queue}) => Navigator.push(context, MaterialPageRoute(builder: (_) => DetailPage(item: item, library: widget.library, player: widget.player, queue: queue)));
  @override Widget build(BuildContext context) => AnimatedBuilder(animation: Listenable.merge([widget.library, widget.player]), builder: (_, __) => Scaffold(
    appBar: AppBar(title: const Row(children: [Icon(Icons.graphic_eq_rounded), SizedBox(width: 8), Text('MusicMan')]), actions: [IconButton(onPressed: widget.onTheme, icon: Icon(widget.dark ? Icons.light_mode_outlined : Icons.dark_mode_outlined)), IconButton(onPressed: () => showModalBottomSheet(context: context, showDragHandle: true, builder: (_) => SettingsSheet(library: widget.library)), icon: const Icon(Icons.settings_outlined))]),
    body: Column(children: [Expanded(child: [HomePage(open: open), SearchPage(open: open), LibraryPage(library: widget.library, open: open)][tab]), if (widget.player.current != null) MiniPlayer(player: widget.player, open: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NowPlaying(player: widget.player, library: widget.library)) ))]),
    bottomNavigationBar: NavigationBar(selectedIndex: tab, onDestinationSelected: (v) => setState(() => tab = v), destinations: const [NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'), NavigationDestination(icon: Icon(Icons.search), label: 'Search'), NavigationDestination(icon: Icon(Icons.library_music_outlined), selectedIcon: Icon(Icons.library_music), label: 'Library')]),
  ));
}

class HomePage extends StatelessWidget {
  const HomePage({super.key, required this.open}); final void Function(MusicItem, {List<MusicItem>? queue}) open;
  @override Widget build(BuildContext context) => RefreshIndicator(onRefresh: () async {}, child: ListView(children: [const SizedBox(height: 8), _Section(title: 'Fresh releases', future: MusicApi.fresh(), open: open), _Section(title: 'Popular now', future: MusicApi.popular(), open: open)]));
}
class _Section extends StatelessWidget {
  const _Section({required this.title, required this.future, required this.open}); final String title; final Future<List<MusicItem>> future; final void Function(MusicItem, {List<MusicItem>? queue}) open;
  @override Widget build(BuildContext context) => FutureBuilder<List<MusicItem>>(future: future, builder: (_, s) { if (s.hasError) return Padding(padding: const EdgeInsets.all(24), child: Text('$title could not load: ${s.error}')); if (!s.hasData) return const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())); final items = s.data!; return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 10), child: Text(title, style: Theme.of(context).textTheme.titleLarge)), SizedBox(height: 208, child: ListView.separated(padding: const EdgeInsets.symmetric(horizontal: 16), scrollDirection: Axis.horizontal, itemCount: items.length, separatorBuilder: (_, __) => const SizedBox(width: 12), itemBuilder: (_, i) => SizedBox(width: 140, child: InkWell(borderRadius: BorderRadius.circular(14), onTap: () => open(items[i], queue: items), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [_Artwork(url: items[i].artwork, size: 140), const SizedBox(height: 7), Text(items[i].title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold)), Text(items[i].artist, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall)])))])]); });
}

class SearchPage extends StatefulWidget { const SearchPage({super.key, required this.open}); final void Function(MusicItem, {List<MusicItem>? queue}) open; @override State<SearchPage> createState() => _SearchPageState(); }
class _SearchPageState extends State<SearchPage> { final c = TextEditingController(); Future<List<MusicItem>>? result; String filter = 'all'; @override void dispose() { c.dispose(); super.dispose(); }
  @override Widget build(BuildContext context) => Column(children: [Padding(padding: const EdgeInsets.all(12), child: SearchBar(controller: c, hintText: 'Songs, albums, artists…', leading: const Icon(Icons.search), trailing: [IconButton(icon: const Icon(Icons.arrow_forward), onPressed: () => setState(() => result = MusicApi.search(c.text.trim()))], onSubmitted: (_) => setState(() => result = MusicApi.search(c.text.trim())))), Padding(padding: const EdgeInsets.only(bottom: 6), child: SegmentedButton<String>(segments: const [ButtonSegment(value: 'all', label: Text('All')), ButtonSegment(value: 'track', label: Text('Songs')), ButtonSegment(value: 'album', label: Text('Albums')), ButtonSegment(value: 'artist', label: Text('Artists'))], selected: {filter}, onSelectionChanged: (s) => setState(() => filter = s.first))), Expanded(child: result == null ? const _Empty(icon: Icons.search, title: 'Find your music', text: 'Search songs, albums and artists.') : FutureBuilder<List<MusicItem>>(future: result, builder: (_, s) { if (s.hasError) return _Empty(icon: Icons.error_outline, title: 'Search failed', text: '${s.error}'); if (!s.hasData) return const Center(child: CircularProgressIndicator()); final items = s.data!.where((e) => filter == 'all' || e.type == filter).toList(); return ListView.builder(itemCount: items.length, itemBuilder: (_, i) => MusicTile(item: items[i], onTap: () => widget.open(items[i], queue: items))); })))]);
}

class LibraryPage extends StatefulWidget { const LibraryPage({super.key, required this.library, required this.open}); final LibraryStore library; final void Function(MusicItem, {List<MusicItem>? queue}) open; @override State<LibraryPage> createState() => _LibraryPageState(); }
class _LibraryPageState extends State<LibraryPage> with SingleTickerProviderStateMixin { late TabController tabs; @override void initState() { super.initState(); tabs = TabController(length: 4, vsync: this); } @override void dispose() { tabs.dispose(); super.dispose(); }
 @override Widget build(BuildContext context) => Column(children: [TabBar(controller: tabs, isScrollable: true, tabs: const [Tab(text: 'Liked'), Tab(text: 'Playlists'), Tab(text: 'Offline'), Tab(text: 'History')]), Expanded(child: TabBarView(controller: tabs, children: [_Items(items: widget.library.likes, open: widget.open, empty: 'No liked songs yet.'), _Playlists(library: widget.library, open: widget.open), _Items(items: widget.library.downloads, open: widget.open, empty: 'No offline tracks yet.'), _Items(items: widget.library.history, open: widget.open, empty: 'Your listening history is empty.')]))]); }
class _Items extends StatelessWidget { const _Items({required this.items, required this.open, required this.empty}); final List<MusicItem> items; final void Function(MusicItem, {List<MusicItem>? queue}) open; final String empty; @override Widget build(BuildContext context) => items.isEmpty ? _Empty(icon: Icons.library_music_outlined, title: 'Nothing here', text: empty) : ListView.builder(itemCount: items.length, itemBuilder: (_, i) => MusicTile(item: items[i], onTap: () => open(items[i], queue: items))); }
class _Playlists extends StatelessWidget { const _Playlists({required this.library, required this.open}); final LibraryStore library; final void Function(MusicItem, {List<MusicItem>? queue}) open; @override Widget build(BuildContext context) => ListView(children: [Padding(padding: const EdgeInsets.all(16), child: FilledButton.icon(onPressed: () async { final name = await _nameDialog(context); if (name != null) library.createPlaylist(name); }, icon: const Icon(Icons.add), label: const Text('New playlist'))), if (library.playlists.isEmpty) const _Empty(icon: Icons.queue_music, title: 'No playlists yet', text: 'Create a playlist to organize your music.'), ...library.playlists.entries.map((e) => ListTile(leading: const CircleAvatar(child: Icon(Icons.queue_music)), title: Text(e.key), subtitle: Text('${e.value.length} tracks'), trailing: const Icon(Icons.chevron_right), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PlaylistPage(name: e.key, items: e.value, open: open)))))]); }

class PlaylistPage extends StatelessWidget { const PlaylistPage({super.key, required this.name, required this.items, required this.open}); final String name; final List<MusicItem> items; final void Function(MusicItem, {List<MusicItem>? queue}) open; @override Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: Text(name)), body: _Items(items: items, open: open, empty: 'This playlist is empty.')); }
class DetailPage extends StatefulWidget { const DetailPage({super.key, required this.item, required this.library, required this.player, this.queue}); final MusicItem item; final LibraryStore library; final PlayerController player; final List<MusicItem>? queue; @override State<DetailPage> createState() => _DetailPageState(); }
class _DetailPageState extends State<DetailPage> { late Future<List<MusicItem>> details; double progress = 0; @override void initState() { super.initState(); details = widget.item.type == 'track' ? MusicApi.lookup(widget.item.id, 'song') : MusicApi.lookup(widget.item.id, widget.item.type == 'artist' ? 'album' : 'song'); }
 Future<void> play(MusicItem item) async { try { if (item.playable == null) { await MusicApi.crawl(item.id); if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Crawl queued. Refresh this track when it is ready.'))); return; } await widget.player.play(item, items: widget.queue); widget.library.played(item); } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'))); } }
 @override Widget build(BuildContext context) { final i = widget.item; return Scaffold(appBar: AppBar(title: Text(i.type == 'track' ? 'Track' : i.type == 'artist' ? 'Artist' : 'Album')), body: ListView(children: [const SizedBox(height: 18), Center(child: _Artwork(url: i.artwork, size: 210)), Padding(padding: const EdgeInsets.fromLTRB(24, 16, 24, 4), child: Text(i.title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall)), if (i.artist.isNotEmpty) Text(i.artist, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Theme.of(context).colorScheme.primary)), Padding(padding: const EdgeInsets.all(16), child: Wrap(alignment: WrapAlignment.center, spacing: 8, children: [FilledButton.icon(onPressed: () => play(i), icon: const Icon(Icons.play_arrow), label: Text(i.playable == null ? 'Crawl / unavailable' : 'Play')), OutlinedButton.icon(onPressed: () => widget.library.toggleLike(i), icon: Icon(widget.library.liked(i) ? Icons.favorite : Icons.favorite_border), label: Text(widget.library.liked(i) ? 'Liked' : 'Like')), OutlinedButton.icon(onPressed: () => _download(i), icon: progress > 0 && progress < 1 ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(value: progress)) : const Icon(Icons.download), label: const Text('Offline')), IconButton(onPressed: () => launchUrl(Uri.parse('https://t.me/musicman_official_bot?start=${i.type}_${i.id}'), mode: LaunchMode.externalApplication), icon: const Icon(Icons.send))])), if (i.type == 'track') _Lyrics(raw: i.raw['lyrics']), FutureBuilder<List<MusicItem>>(future: details, builder: (_, s) { if (!s.hasData) return const Padding(padding: EdgeInsets.all(30), child: Center(child: CircularProgressIndicator())); final tracks = s.data!.where((e) => e.type == 'track' && e.id != i.id).toList(); return tracks.isEmpty ? const SizedBox() : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Padding(padding: const EdgeInsets.all(16), child: Text(i.type == 'track' ? 'More from this release' : 'Tracks', style: Theme.of(context).textTheme.titleLarge)), ...tracks.map((t) => MusicTile(item: t, onTap: () => Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => DetailPage(item: t, library: widget.library, player: widget.player, queue: tracks)))))]); })])); }
 Future<void> _download(MusicItem item) async { try { setState(() => progress = .01); await widget.library.cache(item, (p) { if (mounted) setState(() => progress = p); }); if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Saved for offline playback'))); } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'))); } finally { if (mounted) setState(() => progress = 0); } }
}
class MiniPlayer extends StatelessWidget { const MiniPlayer({super.key, required this.player, required this.open}); final PlayerController player; final VoidCallback open; @override Widget build(BuildContext context) { final t = player.current!; final max = player.audio.duration?.inMilliseconds ?? 1; return Material(color: Theme.of(context).colorScheme.surfaceContainerHigh, child: InkWell(onTap: open, child: Column(mainAxisSize: MainAxisSize.min, children: [LinearProgressIndicator(value: player.audio.position.inMilliseconds / max.clamp(1, 1 << 31), minHeight: 2), ListTile(leading: _Artwork(url: t.artwork, size: 44), title: Text(t.title, maxLines: 1, overflow: TextOverflow.ellipsis), subtitle: Text(t.artist, maxLines: 1, overflow: TextOverflow.ellipsis), trailing: Row(mainAxisSize: MainAxisSize.min, children: [IconButton(onPressed: player.previous, icon: const Icon(Icons.skip_previous)), IconButton(onPressed: player.toggle, icon: Icon(player.playing ? Icons.pause : Icons.play_arrow)), IconButton(onPressed: player.next, icon: const Icon(Icons.skip_next))]) )])); } }
class NowPlaying extends StatelessWidget { const NowPlaying({super.key, required this.player, required this.library}); final PlayerController player; final LibraryStore library; @override Widget build(BuildContext context) => AnimatedBuilder(animation: player, builder: (_, __) { final t = player.current; if (t == null) return const Scaffold(body: _Empty(icon: Icons.music_off, title: 'Nothing playing', text: 'Choose a track to start listening.')); final d = player.audio.duration ?? Duration.zero, p = player.audio.position; return Scaffold(appBar: AppBar(title: const Text('Now playing')), body: Padding(padding: const EdgeInsets.all(24), child: Column(children: [_Artwork(url: t.artwork, size: 300), const SizedBox(height: 28), Text(t.title, style: Theme.of(context).textTheme.headlineSmall, textAlign: TextAlign.center), Text(t.artist, style: Theme.of(context).textTheme.titleMedium), Slider(value: p.inMilliseconds.clamp(0, d.inMilliseconds).toDouble(), max: d.inMilliseconds.toDouble().clamp(1, double.infinity), onChanged: (v) => player.audio.seek(Duration(milliseconds: v.round()))), Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(_time(p)), Text(_time(d))]), Row(mainAxisAlignment: MainAxisAlignment.center, children: [IconButton(iconSize: 42, onPressed: player.previous, icon: const Icon(Icons.skip_previous)), IconButton(iconSize: 64, onPressed: player.toggle, icon: Icon(player.playing ? Icons.pause_circle_filled : Icons.play_circle_filled)), IconButton(iconSize: 42, onPressed: player.next, icon: const Icon(Icons.skip_next))]), const SizedBox(height: 12), Text('Queue · ${player.queue.length}', style: Theme.of(context).textTheme.titleMedium), Expanded(child: ListView(children: player.queue.map((x) => ListTile(selected: x.id == t.id, title: Text(x.title), subtitle: Text(x.artist), onTap: () => player.play(x, items: player.queue))).toList()))]))); }); }
String _time(Duration d) => '${d.inMinutes}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';
class MusicTile extends StatelessWidget { const MusicTile({super.key, required this.item, required this.onTap}); final MusicItem item; final VoidCallback onTap; @override Widget build(BuildContext context) => ListTile(leading: _Artwork(url: item.artwork, size: 52, circle: item.type == 'artist'), title: Text(item.title, maxLines: 1, overflow: TextOverflow.ellipsis), subtitle: Text([item.artist, item.album].where((x) => x.isNotEmpty).join(' · '), maxLines: 1, overflow: TextOverflow.ellipsis), trailing: Icon(item.type == 'track' ? Icons.play_circle_outline : Icons.chevron_right), onTap: onTap); }
class _Artwork extends StatelessWidget { const _Artwork({required this.url, required this.size, this.circle = false}); final String url; final double size; final bool circle; @override Widget build(BuildContext context) { final child = url.isEmpty ? Icon(Icons.music_note, size: size * .42) : Image.network(url, width: size, height: size, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(Icons.music_note, size: size * .42)); return ClipRRect(borderRadius: BorderRadius.circular(circle ? size / 2 : 14), child: SizedBox(width: size, height: size, child: ColoredBox(color: Theme.of(context).colorScheme.surfaceContainerHighest, child: child))); } }
class _Empty extends StatelessWidget { const _Empty({required this.icon, required this.title, required this.text}); final IconData icon; final String title, text; @override Widget build(BuildContext context) => Center(child: Padding(padding: const EdgeInsets.all(32), child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 54, color: Theme.of(context).colorScheme.primary), const SizedBox(height: 12), Text(title, style: Theme.of(context).textTheme.titleLarge), const SizedBox(height: 6), Text(text, textAlign: TextAlign.center)]))); }
class _Lyrics extends StatelessWidget { const _Lyrics({this.raw}); final dynamic raw; @override Widget build(BuildContext context) { String text = ''; if (raw is String) text = raw; else if (raw is Map) text = '${raw['plain'] ?? raw['text'] ?? raw['lyrics'] ?? ''}'; if (text.isEmpty) return const SizedBox(); return Card(margin: const EdgeInsets.all(16), child: Padding(padding: const EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Text('Lyrics', style: TextStyle(fontWeight: FontWeight.bold)), const SizedBox(height: 10), SelectableText(text)]))); } }
class SettingsSheet extends StatelessWidget { const SettingsSheet({super.key, required this.library}); final LibraryStore library; @override Widget build(BuildContext context) => ListView(shrinkWrap: true, children: [const ListTile(title: Text('MusicMan settings'), subtitle: Text('Your library is saved privately on this device.')), ListTile(leading: const Icon(Icons.delete_outline), title: const Text('Clear local library'), onTap: () async { final p = await SharedPreferences.getInstance(); await p.clear(); await library.load(); if (context.mounted) Navigator.pop(context); })]); }
Future<String?> _nameDialog(BuildContext context) async { final c = TextEditingController(); final value = await showDialog<String>(context: context, builder: (ctx) => AlertDialog(title: const Text('New playlist'), content: TextField(controller: c, autofocus: true, decoration: const InputDecoration(labelText: 'Playlist name')), actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(ctx, c.text), child: const Text('Create'))])); c.dispose(); return value; }
