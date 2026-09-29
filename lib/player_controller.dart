import 'dart:async';
import 'dart:io';

import 'package:audio_session/audio_session.dart';
import 'package:flutter/foundation.dart';
import 'package:just_audio/just_audio.dart';

import 'api_service.dart';
import 'models.dart';
import 'storage.dart';

enum MusicRepeatMode { off, all, one }

class ABRepeat {
  ABRepeat({this.a, this.b});
  final double? a; // in seconds
  final double? b; // in seconds
}

class PlayerController extends ChangeNotifier {
  PlayerController({required this.api, required this.store});

  final MusicApi api;
  final AppStore store;

  final AudioPlayer _player = AudioPlayer();

  MusicItem? current;
  List<MusicItem> queue = [];
  int index = -1;

  bool shuffle = false;
  MusicRepeatMode repeat = MusicRepeatMode.off;

  ABRepeat abRepeat = ABRepeat();
  Timer? _sleepTimer;
  DateTime? sleepEndTime;

  TrackLyrics? currentLyrics;
  int currentLyricIndex = -1;

  StreamSubscription? _positionSub;
  StreamSubscription? _playerStateSub;

  int _playbackStartTime = 0;

  AudioPlayer get player => _player;
  bool get isPlaying => _player.playing;
  Duration get position => _player.position;
  Duration get duration => _player.duration ?? Duration.zero;

  Future<void> init() async {
    final session = await AudioSession.instance;
    await session.configure(const AudioSessionConfiguration.music());

    _positionSub = _player.positionStream.listen((pos) {
      _checkABRepeat(pos);
      _updateLyricIndex(pos);
      notifyListeners();
    });

    _playerStateSub = _player.playerStateStream.listen((state) {
      if (state.processingState == ProcessingState.completed) {
        _onTrackEnded();
      }
      notifyListeners();
    });
  }

  Future<void> play(MusicItem item, {List<MusicItem>? queueItems}) async {
    if (queueItems != null && queueItems.isNotEmpty) {
      queue = List.from(queueItems);
      index = queue.indexWhere((e) => e.trackId == item.trackId);
      if (index < 0) index = 0;
    } else {
      if (!queue.any((e) => e.trackId == item.trackId)) {
        queue.add(item);
        index = queue.length - 1;
      } else {
        index = queue.indexWhere((e) => e.trackId == item.trackId);
      }
    }

    current = item;
    currentLyrics = TrackLyrics.parse(item.rawLyrics);
    currentLyricIndex = -1;

    // Check local offline file first
    final localPath = store.getLocalFilePath(item.trackId);
    String playUrl = '';

    if (localPath != null && File(localPath).existsSync()) {
      await _player.setFilePath(localPath);
    } else {
      final playable = item.playableUrl;
      if (playable == null || playable.isEmpty) {
        throw Exception('This track is not ready to play yet.');
      }
      playUrl = api.proxyUrl(playable);
      await _player.setUrl(playUrl);
    }

    _playbackStartTime = DateTime.now().millisecondsSinceEpoch;
    store.recordPlayed(item);

    if (store.settings.autoSavePlayed && !store.isDownloaded(item.trackId) && item.hasAudio) {
      store.downloadAndSave(item, (_) {}).catchError((_) => File(''));
    }

    await _player.play();
    notifyListeners();
  }

  Future<void> togglePlay() async {
    if (_player.playing) {
      await _player.pause();
    } else {
      await _player.play();
    }
    notifyListeners();
  }

  Future<void> seek(Duration pos) async {
    await _player.seek(pos);
    notifyListeners();
  }

  Future<void> next({bool userInitiated = true}) async {
    if (queue.isEmpty) return;
    _recordStats();

    if (repeat == MusicRepeatMode.one && !userInitiated) {
      await _player.seek(Duration.zero);
      await _player.play();
      return;
    }

    int nextIdx;
    if (shuffle && queue.length > 1) {
      nextIdx = (index + 1 + (queue.length > 2 ? 1 : 0)) % queue.length;
    } else {
      nextIdx = index + 1;
      if (nextIdx >= queue.length) {
        if (repeat == MusicRepeatMode.all) {
          nextIdx = 0;
        } else {
          await _player.pause();
          await _player.seek(Duration.zero);
          notifyListeners();
          return;
        }
      }
    }

    index = nextIdx;
    await play(queue[index]);
  }

  Future<void> previous() async {
    if (queue.isEmpty) return;
    _recordStats();

    if (_player.position.inSeconds > 3) {
      await _player.seek(Duration.zero);
      return;
    }

    int prevIdx = index - 1;
    if (prevIdx < 0) {
      prevIdx = repeat == MusicRepeatMode.all ? queue.length - 1 : 0;
    }

    index = prevIdx;
    await play(queue[index]);
  }

  void toggleShuffle() {
    shuffle = !shuffle;
    notifyListeners();
  }

  void cycleRepeat() {
    switch (repeat) {
      case MusicRepeatMode.off:
        repeat = MusicRepeatMode.all;
        break;
      case MusicRepeatMode.all:
        repeat = MusicRepeatMode.one;
        break;
      case MusicRepeatMode.one:
        repeat = MusicRepeatMode.off;
        break;
    }
    notifyListeners();
  }

  void toggleABRepeat() {
    final pos = _player.position.inMilliseconds / 1000.0;
    if (abRepeat.a == null) {
      abRepeat = ABRepeat(a: pos);
    } else if (abRepeat.b == null) {
      if (pos > (abRepeat.a! + 0.5)) {
        abRepeat = ABRepeat(a: abRepeat.a, b: pos);
      } else {
        abRepeat = ABRepeat();
      }
    } else {
      abRepeat = ABRepeat();
    }
    notifyListeners();
  }

  void setSleepTimer(int minutes) {
    _sleepTimer?.cancel();
    if (minutes <= 0) {
      _sleepTimer = null;
      sleepEndTime = null;
    } else {
      sleepEndTime = DateTime.now().add(Duration(minutes: minutes));
      _sleepTimer = Timer(Duration(minutes: minutes), () async {
        if (store.settings.sleepFade) {
          for (int i = 10; i >= 0; i--) {
            await _player.setVolume(i / 10.0);
            await Future.delayed(const Duration(milliseconds: 200));
          }
        }
        await _player.pause();
        await _player.setVolume(1.0);
        _sleepTimer = null;
        sleepEndTime = null;
        notifyListeners();
      });
    }
    notifyListeners();
  }

  void reorderQueue(int oldIndex, int newIndex) {
    if (oldIndex < newIndex) newIndex -= 1;
    final item = queue.removeAt(oldIndex);
    queue.insert(newIndex, item);
    if (index == oldIndex) {
      index = newIndex;
    } else if (index > oldIndex && index <= newIndex) {
      index -= 1;
    } else if (index < oldIndex && index >= newIndex) {
      index += 1;
    }
    notifyListeners();
  }

  void removeFromQueue(int idx) {
    if (idx < 0 || idx >= queue.length) return;
    queue.removeAt(idx);
    if (idx < index) {
      index -= 1;
    } else if (idx == index) {
      if (queue.isNotEmpty) {
        index = index % queue.length;
        play(queue[index]);
      } else {
        current = null;
        index = -1;
        _player.stop();
      }
    }
    notifyListeners();
  }

  void clearQueue() {
    queue = current != null ? [current!] : [];
    index = current != null ? 0 : -1;
    notifyListeners();
  }

  void _checkABRepeat(Duration pos) {
    if (abRepeat.a != null && abRepeat.b != null) {
      final posSec = pos.inMilliseconds / 1000.0;
      if (posSec >= abRepeat.b!) {
        _player.seek(Duration(milliseconds: (abRepeat.a! * 1000).toInt()));
      }
    }
  }

  void _updateLyricIndex(Duration pos) {
    if (currentLyrics == null || !currentLyrics!.isSynced) return;
    final posSec = pos.inMilliseconds / 1000.0;
    int idx = -1;
    for (int i = 0; i < currentLyrics!.lines.length; i++) {
      final lineTime = currentLyrics!.lines[i].time;
      if (lineTime != null && lineTime <= posSec + 0.1) {
        idx = i;
      } else if (lineTime != null && lineTime > posSec + 0.1) {
        break;
      }
    }
    if (idx != currentLyricIndex) {
      currentLyricIndex = idx;
    }
  }

  void _onTrackEnded() {
    next(userInitiated: false);
  }

  void _recordStats() {
    if (current != null && _playbackStartTime > 0) {
      final elapsed = DateTime.now().millisecondsSinceEpoch - _playbackStartTime;
      store.recordListeningTime(current!, elapsed);
      _playbackStartTime = DateTime.now().millisecondsSinceEpoch;
    }
  }

  @override
  void dispose() {
    _recordStats();
    _positionSub?.cancel();
    _playerStateSub?.cancel();
    _sleepTimer?.cancel();
    _player.dispose();
    super.dispose();
  }
}
