import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_service.dart';
import 'main.dart';
import 'models.dart';
import 'player_controller.dart';
import 'storage.dart';

class ItemDetailPage extends StatefulWidget {
  const ItemDetailPage({
    super.key,
    required this.item,
    required this.api,
    required this.store,
    required this.player,
    this.queue,
  });

  final MusicItem item;
  final MusicApi api;
  final AppStore store;
  final PlayerController player;
  final List<MusicItem>? queue;

  @override
  State<ItemDetailPage> createState() => _ItemDetailPageState();
}

class _ItemDetailPageState extends State<ItemDetailPage> {
  late Future<List<MusicItem>> _subTracksFuture;
  CrawlStatus? _crawlStatus;
  double _downloadProgress = 0.0;
  bool _isDownloading = false;

  @override
  void initState() {
    super.initState();
    _loadSubTracks();
    if (widget.item.type == 'track') {
      _checkCrawlStatus();
    }
  }

  void _loadSubTracks() {
    if (widget.item.type == 'artist') {
      _subTracksFuture = widget.api.getArtistTracks(widget.item.id);
    } else if (widget.item.type == 'collection') {
      _subTracksFuture = widget.api.lookup(widget.item.id, 'song');
    } else {
      _subTracksFuture = widget.api.lookup(widget.item.id, 'song');
    }
  }

  Future<void> _checkCrawlStatus() async {
    final status = await widget.api.getCrawlStatus(widget.item.trackId);
    if (mounted) {
      setState(() => _crawlStatus = status);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          item.type == 'track'
              ? 'Track Details'
              : (item.type == 'artist' ? 'Artist' : 'Album'),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          const SizedBox(height: 16),
          Center(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(item.type == 'artist' ? 100 : 16),
              child: item.artworkUrl.isNotEmpty
                  ? Image.network(
                      item.artworkUrl,
                      width: 200,
                      height: 200,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        width: 200,
                        height: 200,
                        color: Colors.grey.withOpacity(0.2),
                        child: const Icon(Icons.music_note, size: 64),
                      ),
                    )
                  : Container(
                      width: 200,
                      height: 200,
                      color: Colors.grey.withOpacity(0.2),
                      child: const Icon(Icons.music_note, size: 64),
                    ),
            ),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Text(
              item.title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
          ),
          if (item.artistName.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                item.artistName,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                    ),
              ),
            ),

          const SizedBox(height: 16),

          // Primary Actions Bar
          _buildActionBar(context),

          if (item.type == 'track') ...[
            _buildCrawlCard(context),
            _buildNoteCard(context),
            _buildLyricsCard(context),
          ],

          const SizedBox(height: 16),

          // Secondary Sub-Tracks or Related Content
          FutureBuilder<List<MusicItem>>(
            future: _subTracksFuture,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('Could not load songs: ${snapshot.error}'),
                );
              }
              if (!snapshot.hasData) {
                return const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(),
                  ),
                );
              }

              final subItems = snapshot.data!
                  .where((e) => e.type == 'track' && e.id != item.id)
                  .toList();

              if (subItems.isEmpty) return const SizedBox.shrink();

              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                    child: Text(
                      item.type == 'track'
                          ? 'More from release'
                          : 'Tracks (${subItems.length})',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                  ),
                  ListView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: subItems.length,
                    itemBuilder: (context, i) {
                      final sub = subItems[i];
                      return MusicTile(
                        item: sub,
                        onTap: () {
                          widget.player.play(sub, queueItems: subItems);
                        },
                      );
                    },
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildActionBar(BuildContext context) {
    final item = widget.item;
    final isDownloaded = widget.store.isDownloaded(item.trackId);

    return Wrap(
      alignment: WrapAlignment.center,
      spacing: 8,
      runSpacing: 8,
      children: [
        FilledButton.icon(
          onPressed: () => _playItem(),
          icon: Icon(item.hasAudio || isDownloaded ? Icons.play_arrow : Icons.cloud_download),
          label: Text(
            item.hasAudio || isDownloaded
                ? 'Play'
                : (item.hasPreview ? 'Preview' : 'Crawl'),
          ),
        ),

        ListenableBuilder(
          listenable: widget.store,
          builder: (context, _) {
            final liked = widget.store.isLiked(item.trackId);
            return OutlinedButton.icon(
              onPressed: () => widget.store.toggleLike(item),
              icon: Icon(liked ? Icons.favorite : Icons.favorite_border,
                  color: liked ? Colors.red : null),
              label: Text(liked ? 'Liked' : 'Like'),
            );
          },
        ),

        if (item.type == 'track')
          OutlinedButton.icon(
            onPressed: _isDownloading ? null : _downloadTrack,
            icon: _isDownloading
                ? SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      value: _downloadProgress > 0 ? _downloadProgress : null,
                      strokeWidth: 2,
                    ),
                  )
                : Icon(isDownloaded ? Icons.check_circle : Icons.download),
            label: Text(isDownloaded ? 'Offline' : 'Save File'),
          ),

        IconButton(
          icon: const Icon(Icons.send),
          tooltip: 'Get in Telegram Bot',
          onPressed: () {
            final url = MusicApi.telegramBotUrl(item.type, item.id);
            launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
          },
        ),
      ],
    );
  }

  Widget _buildCrawlCard(BuildContext context) {
    final item = widget.item;
    final isDownloaded = widget.store.isDownloaded(item.trackId);

    if (isDownloaded) {
      return Card(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        color: Colors.green.withOpacity(0.12),
        child: ListTile(
          leading: const Icon(Icons.cloud_done, color: Colors.green),
          title: const Text('Saved Offline'),
          subtitle: const Text('Ready for offline playback anytime.'),
          trailing: IconButton(
            icon: const Icon(Icons.delete_outline, color: Colors.red),
            onPressed: () => widget.store.deleteDownload(item.trackId),
          ),
        ),
      );
    }

    if (_crawlStatus != null &&
        (_crawlStatus!.downloadStatus == 'pending' ||
            _crawlStatus!.downloadStatus == 'downloading')) {
      return Card(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            children: [
              Row(
                children: [
                  const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                  const SizedBox(width: 12),
                  Text('Crawling on server: ${_crawlStatus!.percent.toInt()}%'),
                ],
              ),
              const SizedBox(height: 8),
              LinearProgressIndicator(value: _crawlStatus!.percent / 100.0),
            ],
          ),
        ),
      );
    }

    if (!item.hasAudio && item.hasPreview) {
      return Card(
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        color: Colors.blue.withOpacity(0.12),
        child: ListTile(
          leading: const Icon(Icons.info_outline, color: Colors.blue),
          title: const Text('Preview Mode'),
          subtitle: const Text('Full audio isn\'t ready. Tap Crawl to fetch full MP3.'),
          trailing: ElevatedButton(
            onPressed: _triggerCrawl,
            child: const Text('Crawl'),
          ),
        ),
      );
    }

    return const SizedBox.shrink();
  }

  Widget _buildNoteCard(BuildContext context) {
    final note = widget.store.getNote(widget.item.trackId);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: ListTile(
        leading: const Icon(Icons.edit_note, color: Colors.deepPurple),
        title: Text(note != null ? 'Note' : 'Add Personal Note'),
        subtitle: Text(note ?? 'Tap to keep notes about this track'),
        trailing: IconButton(
          icon: Icon(note != null ? Icons.edit : Icons.add),
          onPressed: () async {
            final result = await _showTextDialog(
              context,
              'Track Note',
              initial: note ?? '',
            );
            if (result != null) {
              widget.store.setNote(widget.item.trackId, result);
              setState(() {});
            }
          },
        ),
      ),
    );
  }

  Widget _buildLyricsCard(BuildContext context) {
    final lyrics = TrackLyrics.parse(widget.item.rawLyrics);
    if (lyrics.lines.isEmpty) return const SizedBox.shrink();

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.music_note, size: 20),
                const SizedBox(width: 8),
                const Text('Lyrics', style: TextStyle(fontWeight: FontWeight.bold)),
                if (lyrics.isSynced) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'SYNCED',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                  ),
                ],
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.copy, size: 18),
                  onPressed: () {
                    final text = lyrics.lines.map((e) => e.text).join('\n');
                    Clipboard.setData(ClipboardData(text: text));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Lyrics copied to clipboard')),
                    );
                  },
                ),
              ],
            ),
            const SizedBox(height: 8),
            SelectableText(
              lyrics.lines.map((e) => e.text).join('\n'),
              style: const TextStyle(height: 1.6),
            ),
          ],
        ),
      ),
    );
  }

  void _playItem() {
    try {
      widget.player.play(widget.item, queueItems: widget.queue);
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$e')),
      );
    }
  }

  Future<void> _triggerCrawl() async {
    try {
      await widget.api.addCrawlTask(trackId: widget.item.trackId);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Crawl task queued on server!')),
      );
      _checkCrawlStatus();
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Crawl error: $e')),
      );
    }
  }

  Future<void> _downloadTrack() async {
    setState(() {
      _isDownloading = true;
      _downloadProgress = 0.01;
    });

    try {
      await widget.store.downloadAndSave(widget.item, (p) {
        if (mounted) setState(() => _downloadProgress = p);
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Downloaded for offline listening!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Download failed: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isDownloading = false);
      }
    }
  }

  Future<String?> _showTextDialog(BuildContext context, String title, {String initial = ''}) {
    final ctrl = TextEditingController(text: initial);
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: ctrl,
          maxLines: 3,
          autofocus: true,
          decoration: const InputDecoration(hintText: 'Enter note...'),
        ),
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

// -----------------------------------------------------------------------------
// Full Screen Player Page (Tabs: Now Playing, Queue, Lyrics, Info)
// -----------------------------------------------------------------------------
class FullPlayerPage extends StatefulWidget {
  const FullPlayerPage({
    super.key,
    required this.api,
    required this.store,
    required this.player,
  });

  final MusicApi api;
  final AppStore store;
  final PlayerController player;

  @override
  State<FullPlayerPage> createState() => _FullPlayerPageState();
}

class _FullPlayerPageState extends State<FullPlayerPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Playing'),
            Tab(text: 'Queue'),
            Tab(text: 'Lyrics'),
            Tab(text: 'Info'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _PlayingTab(player: widget.player, store: widget.store),
          _QueueTab(player: widget.player),
          _LyricsTab(player: widget.player, store: widget.store),
          _InfoTab(player: widget.player, api: widget.api),
        ],
      ),
    );
  }
}

class _PlayingTab extends StatelessWidget {
  const _PlayingTab({required this.player, required this.store});

  final PlayerController player;
  final AppStore store;

  String _formatTime(Duration d) {
    final mins = d.inMinutes;
    final secs = (d.inSeconds % 60).toString().padLeft(2, '0');
    return '$mins:$secs';
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: player,
      builder: (context, _) {
        final track = player.current;
        if (track == null) {
          return const Center(child: Text('Nothing playing'));
        }

        final pos = player.position;
        final dur = player.duration;
        final isLiked = store.isLiked(track.trackId);

        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          child: Column(
            children: [
              const Spacer(),
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: track.artworkUrl.isNotEmpty
                    ? Image.network(
                        track.artworkUrl,
                        width: 280,
                        height: 280,
                        fit: BoxFit.cover,
                      )
                    : Container(
                        width: 280,
                        height: 280,
                        color: Colors.grey.withOpacity(0.2),
                        child: const Icon(Icons.music_note, size: 80),
                      ),
              ),
              const SizedBox(height: 24),
              Text(
                track.title,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 4),
              Text(
                track.artistName,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: Theme.of(context).colorScheme.primary,
                    ),
              ),
              const Spacer(),

              // Position slider
              Slider(
                value: pos.inMilliseconds
                    .clamp(0, dur.inMilliseconds)
                    .toDouble(),
                max: dur.inMilliseconds.toDouble().clamp(1.0, double.infinity),
                onChanged: (val) {
                  player.seek(Duration(milliseconds: val.round()));
                },
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(_formatTime(pos)),
                    Text(_formatTime(dur)),
                  ],
                ),
              ),

              // Player Controls
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  IconButton(
                    icon: Icon(Icons.shuffle,
                        color: player.shuffle ? Theme.of(context).colorScheme.primary : null),
                    onPressed: () => player.toggleShuffle(),
                  ),
                  IconButton(
                    iconSize: 40,
                    icon: const Icon(Icons.skip_previous),
                    onPressed: () => player.previous(),
                  ),
                  IconButton(
                    iconSize: 64,
                    icon: Icon(player.isPlaying
                        ? Icons.pause_circle_filled
                        : Icons.play_circle_filled),
                    onPressed: () => player.togglePlay(),
                  ),
                  IconButton(
                    iconSize: 40,
                    icon: const Icon(Icons.skip_next),
                    onPressed: () => player.next(),
                  ),
                  IconButton(
                    icon: Icon(
                      player.repeat == MusicRepeatMode.one
                          ? Icons.repeat_one
                          : Icons.repeat,
                      color: player.repeat != MusicRepeatMode.off
                          ? Theme.of(context).colorScheme.primary
                          : null,
                    ),
                    onPressed: () => player.cycleRepeat(),
                  ),
                ],
              ),

              // Actions Row (Like, A-B, Sleep)
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconButton(
                    icon: Icon(isLiked ? Icons.favorite : Icons.favorite_border,
                        color: isLiked ? Colors.red : null),
                    onPressed: () => store.toggleLike(track),
                  ),
                  TextButton(
                    onPressed: () => player.toggleABRepeat(),
                    child: Text(
                      player.abRepeat.a == null
                          ? 'A-B'
                          : (player.abRepeat.b == null
                              ? 'A: ${_formatTime(Duration(seconds: player.abRepeat.a!.toInt()))}'
                              : 'A-B Active'),
                    ),
                  ),
                ],
              ),
              const Spacer(),
            ],
          ),
        );
      },
    );
  }
}

class _QueueTab extends StatelessWidget {
  const _QueueTab({required this.player});

  final PlayerController player;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: player,
      builder: (context, _) {
        final queue = player.queue;
        if (queue.isEmpty) {
          return const Center(child: Text('Queue is empty'));
        }

        return ReorderableListView.builder(
          itemCount: queue.length,
          onReorder: (oldIdx, newIdx) => player.reorderQueue(oldIdx, newIdx),
          itemBuilder: (context, i) {
            final item = queue[i];
            final isPlaying = i == player.index;

            return ListTile(
              key: ValueKey('${item.trackId}_$i'),
              selected: isPlaying,
              leading: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: item.artworkUrl.isNotEmpty
                    ? Image.network(item.artworkUrl, width: 40, height: 40, fit: BoxFit.cover)
                    : const Icon(Icons.music_note),
              ),
              title: Text(item.title, maxLines: 1, overflow: TextOverflow.ellipsis),
              subtitle: Text(item.artistName, maxLines: 1, overflow: TextOverflow.ellipsis),
              trailing: IconButton(
                icon: const Icon(Icons.close, size: 18),
                onPressed: () => player.removeFromQueue(i),
              ),
              onTap: () => player.play(item, queueItems: queue),
            );
          },
        );
      },
    );
  }
}

class _LyricsTab extends StatelessWidget {
  const _LyricsTab({required this.player, required this.store});

  final PlayerController player;
  final AppStore store;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: player,
      builder: (context, _) {
        final lyrics = player.currentLyrics;
        if (lyrics == null || lyrics.lines.isEmpty) {
          return const Center(child: Text('No lyrics available'));
        }

        return ListView.builder(
          padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
          itemCount: lyrics.lines.length,
          itemBuilder: (context, i) {
            final line = lyrics.lines[i];
            final isCurrent = i == player.currentLyricIndex;

            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: AnimatedDefaultTextStyle(
                duration: const Duration(milliseconds: 200),
                style: TextStyle(
                  fontSize: isCurrent ? 20 : 16,
                  fontWeight: isCurrent ? FontWeight.bold : FontWeight.normal,
                  color: isCurrent
                      ? Theme.of(context).colorScheme.primary
                      : Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                ),
                child: Text(
                  line.text,
                  textAlign: TextAlign.center,
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _InfoTab extends StatelessWidget {
  const _InfoTab({required this.player, required this.api});

  final PlayerController player;
  final MusicApi api;

  @override
  Widget build(BuildContext context) {
    final track = player.current;
    if (track == null) return const Center(child: Text('No track loaded'));

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _infoRow('Title', track.title),
        _infoRow('Artist', track.artistName),
        _infoRow('Album', track.collectionName),
        _infoRow('Genre', track.primaryGenreName),
        _infoRow('Release Date', track.releaseDate),
        _infoRow('Track ID', track.trackId),
        const SizedBox(height: 20),
        ElevatedButton.icon(
          onPressed: () {
            final url = MusicApi.telegramBotUrl(track.type, track.id);
            launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
          },
          icon: const Icon(Icons.send),
          label: const Text('Get full file in Telegram Bot'),
        ),
      ],
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
          Text(value.isNotEmpty ? value : '—'),
        ],
      ),
    );
  }
}
