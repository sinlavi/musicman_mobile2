import 'package:flutter_test/flutter_test.dart';
import 'package:musicman/models.dart';

void main() {
  group('MusicItem model tests', () {
    test('parses track item correctly', () {
      final json = {
        'wrapperType': 'track',
        'trackId': 12345,
        'trackName': 'Test Song',
        'artistName': 'Test Artist',
        'collectionName': 'Test Album',
        'artworkUrl100': 'https://example.com/100x100bb.jpg',
        'attachments': {
          'audioUrls': [
            {'url': 'https://example.com/audio.mp3', 'quality': '320'}
          ]
        }
      };

      final item = MusicItem(json);
      expect(item.trackId, '12345');
      expect(item.title, 'Test Song');
      expect(item.artistName, 'Test Artist');
      expect(item.type, 'track');
      expect(item.artworkUrl, 'https://example.com/600x600bb.jpg');
      expect(item.hasAudio, isTrue);
      expect(item.playableUrl, 'https://example.com/audio.mp3');
    });

    test('parses synced lyrics correctly', () {
      final lyricsJson = {
        'synced': '[00:12.50] Hello world\n[00:15.00] Second line'
      };

      final lyrics = TrackLyrics.parse(lyricsJson);
      expect(lyrics.isSynced, isTrue);
      expect(lyrics.lines.length, 2);
      expect(lyrics.lines.first.time, 12.5);
      expect(lyrics.lines.first.text, 'Hello world');
    });
  });
}
