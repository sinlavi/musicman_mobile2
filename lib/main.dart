import 'dart:async';

import 'package:flutter/material.dart';

import 'api_service.dart';
import 'detail_pages.dart';
import 'library_views.dart';
import 'models.dart';
import 'player_controller.dart';
import 'storage.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const MusicManApp());
}

class MusicManApp extends StatefulWidget {
  const MusicManApp({super.key});

  @override
  State<MusicManApp> createState() => _MusicManAppState();
}

class _MusicManAppState extends State<MusicManApp> {
  final MusicApi api = MusicApi();
  final AppStore store = AppStore();
  late final PlayerController player;

  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    player = PlayerController(api: api, store: store);
    _initApp();
  }

  Future<void> _initApp() async {
    await store.load();
    await player.init();
    if (mounted) {
      setState(() => _initialized = true);
    }
  }

  @override
  void dispose() {
    player.dispose();
    super.dispose();
  }

  Color _getAccentColor(String accentKey) {
    switch (accentKey) {
      case 'blue':
        return Colors.blue;
      case 'green':
        return Colors.teal;
      case 'orange':
        return Colors.deepOrange;
      case 'pink':
        return Colors.pink;
      case 'purple':
      default:
        return const Color(0xFF7B2FF7);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_initialized) {
      return const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: Scaffold(
          body: Center(
            child: CircularProgressIndicator(),
          ),
        ),
      );
    }

    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final settings = store.settings;
        final isDark = settings.theme == 'dark' ||
            (settings.autoTheme &&
                (DateTime.now().hour < 7 || DateTime.now().hour >= 19));
        final primaryColor = _getAccentColor(settings.accent);

        return MaterialApp(
          title: 'MusicMan',
          debugShowCheckedModeBanner: false,
          theme: ThemeData(
            colorSchemeSeed: primaryColor,
            brightness: isDark ? Brightness.dark : Brightness.light,
            useMaterial3: true,
            scaffoldBackgroundColor: isDark ? const Color(0xFF0B0B0D) : null,
          ),
          home: AppShell(api: api, store: store, player: player),
        );
      },
    );
  }
}

class AppShell extends StatefulWidget {
  const AppShell({
    super.key,
    required this.api,
    required this.store,
    required this.player,
  });

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int _tabIndex = 0;

  @override
  Widget build(BuildContext context) {
    final isDesktop = MediaQuery.of(context).size.width >= 1024;

    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: [
            Icon(Icons.graphic_eq, color: Color(0xFF14B8A6)),
            SizedBox(width: 10),
            Text('MusicMan', style: TextStyle(fontWeight: FontWeight.bold)),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => _showSettingsSheet(context),
          ),
        ],
      ),
      body: Row(
        children: [
          if (isDesktop)
            NavigationRail(
              selectedIndex: _tabIndex,
              onDestinationSelected: (v) => setState(() => _tabIndex = v),
              labelType: NavigationRailLabelType.all,
              destinations: const [
                NavigationRailDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home),
                  label: Text('Home'),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.search_outlined),
                  selectedIcon: Icon(Icons.search),
                  label: Text('Search'),
                ),
                NavigationRailDestination(
                  icon: Icon(Icons.library_music_outlined),
                  selectedIcon: Icon(Icons.library_music),
                  label: Text('Library'),
                ),
              ],
            ),
          Expanded(
            child: Column(
              children: [
                Expanded(
                  child: IndexedStack(
                    index: _tabIndex,
                    children: [
                      HomePage(api: widget.api, store: widget.store, player: widget.player),
                      SearchPage(api: widget.api, store: widget.store, player: widget.player),
                      FullLibraryView(api: widget.api, store: widget.store, player: widget.player),
                    ],
                  ),
                ),
                ListenableBuilder(
                  listenable: widget.player,
                  builder: (context, _) {
                    if (widget.player.current == null) return const SizedBox.shrink();
                    return MiniPlayerBar(
                      player: widget.player,
                      store: widget.store,
                      onTap: () => _openNowPlaying(context),
                    );
                  },
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: isDesktop
          ? null
          : NavigationBar(
              selectedIndex: _tabIndex,
              onDestinationSelected: (v) => setState(() => _tabIndex = v),
              destinations: const [
                NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home),
                  label: 'Home',
                ),
                NavigationDestination(
                  icon: Icon(Icons.search_outlined),
                  selectedIcon: Icon(Icons.search),
                  label: 'Search',
                ),
                NavigationDestination(
                  icon: Icon(Icons.library_music_outlined),
                  selectedIcon: Icon(Icons.library_music),
                  label: 'Library',
                ),
              ],
            ),
    );
  }

  void _openNowPlaying(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => FullPlayerPage(
          api: widget.api,
          store: widget.store,
          player: widget.player,
        ),
      ),
    );
  }

  void _showSettingsSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => FullSettingsSheet(store: widget.store),
    );
  }
}

// -----------------------------------------------------------------------------
// Mini Player Bar
// -----------------------------------------------------------------------------
class MiniPlayerBar extends StatelessWidget {
  const MiniPlayerBar({
    super.key,
    required this.player,
    required this.store,
    required this.onTap,
  });

  final PlayerController player;
  final AppStore store;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final track = player.current;
    if (track == null) return const SizedBox.shrink();

    final dur = player.duration.inMilliseconds;
    final pos = player.position.inMilliseconds;
    final progress = dur > 0 ? (pos / dur).clamp(0.0, 1.0) : 0.0;

    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerHigh,
      elevation: 6,
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            LinearProgressIndicator(
              value: progress,
              minHeight: 2.5,
              backgroundColor: Colors.transparent,
            ),
            ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: track.artworkUrl.isNotEmpty
                    ? Image.network(
                        track.artworkUrl,
                        width: 44,
                        height: 44,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const Icon(Icons.music_note),
                      )
                    : const Icon(Icons.music_note, size: 32),
              ),
              title: Text(
                track.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              subtitle: Text(
                track.artistName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12),
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: Icon(player.isPlaying ? Icons.pause : Icons.play_arrow),
                    onPressed: () => player.togglePlay(),
                  ),
                  IconButton(
                    icon: const Icon(Icons.skip_next),
                    onPressed: () => player.next(),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Home Page
// -----------------------------------------------------------------------------
class HomePage extends StatefulWidget {
  const HomePage({
    super.key,
    required this.api,
    required this.store,
    required this.player,
  });

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  late Future<List<MusicItem>> _freshFuture;
  late Future<List<MusicItem>> _popularFuture;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  void _loadData() {
    _freshFuture = widget.api.fresh();
    _popularFuture = widget.api.popular();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        setState(() => _loadData());
      },
      child: ListView(
        padding: const EdgeInsets.symmetric(vertical: 12),
        children: [
          _HorizontalSection(
            title: 'Fresh Releases',
            future: _freshFuture,
            api: widget.api,
            store: widget.store,
            player: widget.player,
          ),
          const SizedBox(height: 16),
          _HorizontalSection(
            title: 'Popular Now',
            future: _popularFuture,
            api: widget.api,
            store: widget.store,
            player: widget.player,
          ),
        ],
      ),
    );
  }
}

class _HorizontalSection extends StatelessWidget {
  const _HorizontalSection({
    required this.title,
    required this.future,
    required this.api,
    required this.store,
    required this.player,
  });

  final String title;
  final Future<List<MusicItem>> future;
  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
        ),
        SizedBox(
          height: 200,
          child: FutureBuilder<List<MusicItem>>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Center(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text('Error loading: ${snapshot.error}'),
                  ),
                );
              }
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }

              final items = snapshot.data!;
              if (items.isEmpty) {
                return const Center(child: Text('No items found'));
              }

              return ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                scrollDirection: Axis.horizontal,
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final item = items[index];
                  return SizedBox(
                    width: 135,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => ItemDetailPage(
                              item: item,
                              api: api,
                              store: store,
                              player: player,
                              queue: items,
                            ),
                          ),
                        );
                      },
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: item.artworkUrl.isNotEmpty
                                ? Image.network(
                                    item.artworkUrl,
                                    width: 135,
                                    height: 135,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, __, ___) =>
                                        Container(
                                      width: 135,
                                      height: 135,
                                      color: Colors.grey.withValues(alpha: 0.2),
                                      child: const Icon(Icons.music_note, size: 48),
                                    ),
                                  )
                                : Container(
                                    width: 135,
                                    height: 135,
                                    color: Colors.grey.withValues(alpha: 0.2),
                                    child: const Icon(Icons.music_note, size: 48),
                                  ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            item.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          Text(
                            item.artistName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              color: Theme.of(context).colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              );
            },
          ),
        ),
      ],
    );
  }
}

// -----------------------------------------------------------------------------
// Search Page with Autocomplete & Filter Chips
// -----------------------------------------------------------------------------
class SearchPage extends StatefulWidget {
  const SearchPage({
    super.key,
    required this.api,
    required this.store,
    required this.player,
  });

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  State<SearchPage> createState() => _SearchPageState();
}

class _SearchPageState extends State<SearchPage> {
  final TextEditingController _searchCtrl = TextEditingController();
  Timer? _debounceTimer;

  List<SearchSuggestion> _suggestions = [];
  Future<List<MusicItem>>? _searchResults;
  String _selectedFilter = 'all'; // all, track, collection, artist
  bool _isSuggesting = false;

  @override
  void dispose() {
    _searchCtrl.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchInputChanged(String text) {
    _debounceTimer?.cancel();
    if (text.trim().isEmpty) {
      setState(() {
        _suggestions = [];
        _isSuggesting = false;
      });
      return;
    }

    _debounceTimer = Timer(const Duration(milliseconds: 250), () async {
      try {
        final suggestions = await widget.api.suggest(text.trim());
        if (mounted && _searchCtrl.text.trim() == text.trim()) {
          setState(() {
            _suggestions = suggestions;
            _isSuggesting = suggestions.isNotEmpty;
          });
        }
      } catch (_) {}
    });
  }

  void _performSearch(String query) {
    if (query.trim().isEmpty) return;
    FocusScope.of(context).unfocus();
    widget.store.addRecentSearch(query.trim());
    setState(() {
      _suggestions = [];
      _isSuggesting = false;
      _searchResults = widget.api.search(query.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: SearchBar(
            controller: _searchCtrl,
            hintText: 'Search artists, tracks, albums...',
            leading: const Icon(Icons.search),
            trailing: [
              if (_searchCtrl.text.isNotEmpty)
                IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _searchCtrl.clear();
                    setState(() {
                      _suggestions = [];
                      _isSuggesting = false;
                      _searchResults = null;
                    });
                  },
                ),
            ],
            onChanged: _onSearchInputChanged,
            onSubmitted: _performSearch,
          ),
        ),

        // Filter chips
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              _filterChip('all', 'All'),
              const SizedBox(width: 8),
              _filterChip('track', 'Songs'),
              const SizedBox(width: 8),
              _filterChip('collection', 'Albums'),
              const SizedBox(width: 8),
              _filterChip('artist', 'Artists'),
            ],
          ),
        ),

        const SizedBox(height: 8),

        Expanded(
          child: Stack(
            children: [
              // Main Search Results or Empty State
              _searchResults == null
                  ? _buildRecentSearches()
                  : FutureBuilder<List<MusicItem>>(
                      future: _searchResults,
                      builder: (context, snapshot) {
                        if (snapshot.hasError) {
                          return Center(
                            child: Text('Search error: ${snapshot.error}'),
                          );
                        }
                        if (!snapshot.hasData) {
                          return const Center(child: CircularProgressIndicator());
                        }

                        final allItems = snapshot.data!;
                        final items = _selectedFilter == 'all'
                            ? allItems
                            : allItems
                                .where((e) => e.type == _selectedFilter)
                                .toList();

                        if (items.isEmpty) {
                          return const Center(child: Text('No results found.'));
                        }

                        return ListView.builder(
                          itemCount: items.length,
                          itemBuilder: (context, i) {
                            final item = items[i];
                            return MusicTile(
                              item: item,
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ItemDetailPage(
                                      item: item,
                                      api: widget.api,
                                      store: widget.store,
                                      player: widget.player,
                                      queue: items,
                                    ),
                                  ),
                                );
                              },
                            );
                          },
                        );
                      },
                    ),

              // Live Suggestion Popup Overlay
              if (_isSuggesting)
                Positioned(
                  top: 0,
                  left: 12,
                  right: 12,
                  child: Material(
                    elevation: 8,
                    borderRadius: BorderRadius.circular(12),
                    color: Theme.of(context).colorScheme.surfaceContainerHigh,
                    child: ListView.separated(
                      shrinkWrap: true,
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      itemCount: _suggestions.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, i) {
                        final sug = _suggestions[i];
                        return ListTile(
                          leading: Icon(
                            sug.type == 'artist'
                                ? Icons.person
                                : (sug.type == 'collection'
                                    ? Icons.album
                                    : Icons.music_note),
                          ),
                          title: Text(sug.name),
                          trailing: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Theme.of(context)
                                  .colorScheme
                                  .primary
                                  .withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              sug.type.toUpperCase(),
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                          ),
                          onTap: () {
                            _searchCtrl.text = sug.name;
                            _performSearch(sug.name);
                          },
                        );
                      },
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _filterChip(String key, String label) {
    final selected = _selectedFilter == key;
    return FilterChip(
      selected: selected,
      label: Text(label),
      onSelected: (_) => setState(() => _selectedFilter = key),
    );
  }

  Widget _buildRecentSearches() {
    final recents = widget.store.recentSearches;
    if (recents.isEmpty) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.search, size: 64, color: Colors.grey),
            SizedBox(height: 12),
            Text('Search MusicMan catalog'),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Recent Searches',
                  style: TextStyle(fontWeight: FontWeight.bold)),
              TextButton(
                onPressed: () => setState(() => widget.store.clearRecentSearches()),
                child: const Text('Clear'),
              ),
            ],
          ),
          Wrap(
            spacing: 8,
            children: recents
                .map((term) => ActionChip(
                      label: Text(term),
                      onPressed: () {
                        _searchCtrl.text = term;
                        _performSearch(term);
                      },
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Music Tile Widget
// -----------------------------------------------------------------------------
class MusicTile extends StatelessWidget {
  const MusicTile({
    super.key,
    required this.item,
    required this.onTap,
    this.onMore,
    this.trailing,
  });

  final MusicItem item;
  final VoidCallback onTap;
  final VoidCallback? onMore;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(item.type == 'artist' ? 24 : 8),
        child: item.artworkUrl.isNotEmpty
            ? Image.network(
                item.artworkUrl,
                width: 48,
                height: 48,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) =>
                    const Icon(Icons.music_note, size: 32),
              )
            : const Icon(Icons.music_note, size: 32),
      ),
      title: Text(
        item.title,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.bold),
      ),
      subtitle: Text(
        [item.artistName, item.collectionName]
            .where((e) => e.isNotEmpty)
            .join(' · '),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: trailing ??
          (onMore != null
              ? IconButton(
                  icon: const Icon(Icons.more_vert),
                  onPressed: onMore,
                )
              : null),
    );
  }
}
