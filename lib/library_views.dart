import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';

import 'api_service.dart';
import 'detail_pages.dart';
import 'main.dart';
import 'models.dart';
import 'player_controller.dart';
import 'storage.dart';

class FullLibraryView extends StatelessWidget {
  const FullLibraryView({
    super.key,
    required this.api,
    required this.store,
    required this.player,
  });

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 6,
      child: Scaffold(
        appBar: const TabBar(
          isScrollable: true,
          tabs: [
            Tab(text: 'Liked'),
            Tab(text: 'Following'),
            Tab(text: 'Playlists'),
            Tab(text: 'Offline'),
            Tab(text: 'Stats'),
            Tab(text: 'History'),
          ],
        ),
        body: TabBarView(
          children: [
            _LikedTab(api: api, store: store, player: player),
            _FollowingTab(api: api, store: store, player: player),
            _PlaylistsTab(api: api, store: store, player: player),
            _OfflineTab(api: api, store: store, player: player),
            _StatsTab(store: store),
            _HistoryTab(api: api, store: store, player: player),
          ],
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Liked Songs Tab
// -----------------------------------------------------------------------------
class _LikedTab extends StatelessWidget {
  const _LikedTab({required this.api, required this.store, required this.player});

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final likes = store.likes;
        if (likes.isEmpty) {
          return const Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.favorite_border, size: 64, color: Colors.grey),
                SizedBox(height: 12),
                Text('No liked songs yet.'),
              ],
            ),
          );
        }

        return ListView(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Text(
                    '${likes.length} Liked Songs',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const Spacer(),
                  FilledButton.icon(
                    onPressed: () => player.play(likes.first, queueItems: likes),
                    icon: const Icon(Icons.play_arrow),
                    label: const Text('Play All'),
                  ),
                ],
              ),
            ),
            ...likes.map(
              (item) => MusicTile(
                item: item,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ItemDetailPage(
                        item: item,
                        api: api,
                        store: store,
                        player: player,
                        queue: likes,
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        );
      },
    );
  }
}

// -----------------------------------------------------------------------------
// Following Artists Tab
// -----------------------------------------------------------------------------
class _FollowingTab extends StatelessWidget {
  const _FollowingTab({required this.api, required this.store, required this.player});

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final following = store.following;
        if (following.isEmpty) {
          return const Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.people_outline, size: 64, color: Colors.grey),
                SizedBox(height: 12),
                Text('Not following any artists.'),
              ],
            ),
          );
        }

        return GridView.builder(
          padding: const EdgeInsets.all(16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            mainAxisSpacing: 16,
            crossAxisSpacing: 16,
            childAspectRatio: 0.8,
          ),
          itemCount: following.length,
          itemBuilder: (context, i) {
            final artist = following[i];
            final item = MusicItem({
              'wrapperType': 'artist',
              'artistId': artist.artistId,
              'artistName': artist.artistName,
              'artworkUrl100': artist.artwork,
              'primaryGenreName': artist.primaryGenreName,
            });

            return InkWell(
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
                    ),
                  ),
                );
              },
              child: Column(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(50),
                    child: artist.artwork.isNotEmpty
                        ? Image.network(artist.artwork, width: 80, height: 80, fit: BoxFit.cover)
                        : Container(
                            width: 80,
                            height: 80,
                            color: Colors.grey.withValues(alpha: 0.2),
                            child: const Icon(Icons.person, size: 40),
                          ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    artist.artistName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

// -----------------------------------------------------------------------------
// Playlists & Smart Playlists Tab
// -----------------------------------------------------------------------------
class _PlaylistsTab extends StatelessWidget {
  const _PlaylistsTab({required this.api, required this.store, required this.player});

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final pls = store.playlists;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Smart Playlists Section
            const Text(
              'Smart Playlists',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Colors.purple,
                child: Icon(Icons.shuffle, color: Colors.white),
              ),
              title: const Text('Shuffle All Liked'),
              subtitle: Text('${store.likes.length} tracks'),
              onTap: () {
                if (store.likes.isNotEmpty) {
                  player.play(store.likes.first, queueItems: store.likes);
                  player.toggleShuffle();
                }
              },
            ),
            ListTile(
              leading: const CircleAvatar(
                backgroundColor: Colors.orange,
                child: Icon(Icons.local_fire_department, color: Colors.white),
              ),
              title: const Text('Most Played'),
              subtitle: Text('${store.stats.tracks.length} tracks recorded'),
            ),
            const Divider(height: 32),

            // User Playlists Section
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Your Playlists',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                FilledButton.icon(
                  onPressed: () async {
                    final name = await _showInputDialog(context, 'New Playlist');
                    if (name != null) store.createPlaylist(name);
                  },
                  icon: const Icon(Icons.add),
                  label: const Text('New'),
                ),
              ],
            ),
            const SizedBox(height: 8),

            if (pls.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(child: Text('No playlists created yet.')),
              )
            else
              ...pls.map(
                (p) => ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.queue_music)),
                  title: Text(p.name),
                  subtitle: Text('${p.tracks.length} tracks'),
                  trailing: IconButton(
                    icon: const Icon(Icons.delete_outline, color: Colors.red),
                    onPressed: () => store.deletePlaylist(p.id),
                  ),
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => PlaylistDetailPage(
                          playlist: p,
                          api: api,
                          store: store,
                          player: player,
                        ),
                      ),
                    );
                  },
                ),
              ),
          ],
        );
      },
    );
  }

  Future<String?> _showInputDialog(BuildContext context, String title) {
    final ctrl = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(controller: ctrl, autofocus: true),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, ctrl.text),
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }
}

class PlaylistDetailPage extends StatelessWidget {
  const PlaylistDetailPage({
    super.key,
    required this.playlist,
    required this.api,
    required this.store,
    required this.player,
  });

  final Playlist playlist;
  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(playlist.name)),
      body: playlist.tracks.isEmpty
          ? const Center(child: Text('Playlist is empty.'))
          : ListView.builder(
              itemCount: playlist.tracks.length,
              itemBuilder: (context, i) {
                final item = playlist.tracks[i];
                return MusicTile(
                  item: item,
                  onTap: () => player.play(item, queueItems: playlist.tracks),
                  trailing: IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => store.removeFromPlaylist(playlist.id, item.trackId),
                  ),
                );
              },
            ),
    );
  }
}

// -----------------------------------------------------------------------------
// Offline Downloads Manager Tab
// -----------------------------------------------------------------------------
class _OfflineTab extends StatelessWidget {
  const _OfflineTab({required this.api, required this.store, required this.player});

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final downloads = store.downloads;
        if (downloads.isEmpty) {
          return const Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.cloud_off, size: 64, color: Colors.grey),
                SizedBox(height: 12),
                Text('No downloaded tracks.'),
              ],
            ),
          );
        }

        return ListView(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Text(
                    '${downloads.length} Saved Tracks',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  const Spacer(),
                  TextButton.icon(
                    onPressed: () => store.clearAllDownloads(),
                    icon: const Icon(Icons.delete, color: Colors.red),
                    label: const Text('Clear All', style: TextStyle(color: Colors.red)),
                  ),
                ],
              ),
            ),
            ...downloads.map(
              (dl) => MusicTile(
                item: dl.item,
                onTap: () => player.play(dl.item),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.file_download, color: Colors.blue),
                      tooltip: 'Export MP3 file',
                      onPressed: () => _exportFile(context, dl),
                    ),
                    IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () => store.deleteDownload(dl.item.trackId),
                    ),
                  ],
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  Future<void> _exportFile(BuildContext context, LocalDownloadItem dl) async {
    try {
      final extDir = await getExternalStorageDirectory();
      if (extDir != null) {
        final dest = File('${extDir.path}/${dl.item.title}.mp3');
        await File(dl.filePath).copy(dest.path);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Exported to ${dest.path}')),
          );
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Export error: $e')),
        );
      }
    }
  }
}

// -----------------------------------------------------------------------------
// Listening Stats Dashboard
// -----------------------------------------------------------------------------
class _StatsTab extends StatelessWidget {
  const _StatsTab({required this.store});

  final AppStore store;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final stats = store.stats;
        final mins = (stats.totalMs / 60000).round();

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    const Text('Total Listening Time', style: TextStyle(color: Colors.grey)),
                    const SizedBox(height: 8),
                    Text(
                      '$mins Minutes',
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text('Total Plays: ${stats.plays}'),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Top Tracks', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                TextButton(
                  onPressed: () => store.resetStats(),
                  child: const Text('Reset Stats', style: TextStyle(color: Colors.red)),
                ),
              ],
            ),
            ...stats.tracks.values.map(
              (t) => ListTile(
                title: Text(t.name),
                subtitle: Text(t.artist),
                trailing: Text('${t.count} plays'),
              ),
            ),
          ],
        );
      },
    );
  }
}

// -----------------------------------------------------------------------------
// History Log
// -----------------------------------------------------------------------------
class _HistoryTab extends StatelessWidget {
  const _HistoryTab({required this.api, required this.store, required this.player});

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: store,
      builder: (context, _) {
        final history = store.history;
        if (history.isEmpty) {
          return const Center(child: Text('Listening history is empty.'));
        }

        return ListView(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('${history.length} Recently Played',
                      style: const TextStyle(fontWeight: FontWeight.bold)),
                  TextButton(
                    onPressed: () => store.clearHistory(),
                    child: const Text('Clear'),
                  ),
                ],
              ),
            ),
            ...history.map(
              (item) => MusicTile(
                item: item,
                onTap: () => player.play(item, queueItems: history),
              ),
            ),
          ],
        );
      },
    );
  }
}

// -----------------------------------------------------------------------------
// Settings Sheet with Theme, Accent & Backup Export/Import
// -----------------------------------------------------------------------------
class FullSettingsSheet extends StatelessWidget {
  const FullSettingsSheet({super.key, required this.store});

  final AppStore store;

  @override
  Widget build(BuildContext context) {
    final settings = store.settings;

    return ListView(
      shrinkWrap: true,
      padding: const EdgeInsets.all(16),
      children: [
        Text('Appearance', style: Theme.of(context).textTheme.titleLarge),
        SwitchListTile(
          title: const Text('Dark Mode'),
          value: settings.theme == 'dark',
          onChanged: (val) {
            settings.theme = val ? 'dark' : 'light';
            store.updateSettings(settings);
          },
        ),
        SwitchListTile(
          title: const Text('Auto Theme (Day/Night)'),
          value: settings.autoTheme,
          onChanged: (val) {
            settings.autoTheme = val;
            store.updateSettings(settings);
          },
        ),
        const Divider(),
        Text('Playback', style: Theme.of(context).textTheme.titleLarge),
        SwitchListTile(
          title: const Text('Auto-save Played Tracks'),
          subtitle: const Text('Silently download tracks you listen to'),
          value: settings.autoSavePlayed,
          onChanged: (val) {
            settings.autoSavePlayed = val;
            store.updateSettings(settings);
          },
        ),
        const Divider(),
        Text('Backup & Storage', style: Theme.of(context).textTheme.titleLarge),
        ListTile(
          leading: const Icon(Icons.download),
          title: const Text('Export Backup JSON'),
          onTap: () {
            final jsonStr = store.exportBackupJson();
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Backup JSON generated (${jsonStr.length} chars).')),
            );
          },
        ),
        ListTile(
          leading: const Icon(Icons.delete_forever, color: Colors.red),
          title: const Text('Reset All App Data'),
          onTap: () async {
            await store.resetAllData();
            if (context.mounted) Navigator.pop(context);
          },
        ),
      ],
    );
  }
}
