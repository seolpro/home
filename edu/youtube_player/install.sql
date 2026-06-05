-- YouTube 교육영상 재생관 설치 SQL
-- phpMyAdmin에서 이 파일 내용을 실행하세요.

CREATE TABLE IF NOT EXISTS yt_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS yt_videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  youtube_url TEXT NOT NULL,
  youtube_id VARCHAR(20) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO yt_settings (setting_key, setting_value) VALUES
('site_eyebrow', 'EDUCATION VIDEO'),
('site_title', '교육영상 모음'),
('site_subtitle', '이 영상 자료는 교육 관련 보조자료로 활용해 주세요.'),
('max_videos', '20'),
('autoplay', '1'),
('mute', '0'),
('show_list', '1')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- 샘플/이전 교육영상 목록
INSERT INTO yt_videos (title, youtube_url, youtube_id, slug, description, sort_order, is_active) VALUES
('구글설문지 배포 및 URL단축', 'https://www.youtube.com/watch?v=8DAeJTLku34', '8DAeJTLku34', 'google-form-url', '', 10, 1),
('알림톡생성 및 활용(뿌리오에서)', 'https://www.youtube.com/watch?v=PuFy4v-1zHM', 'PuFy4v-1zHM', 'ppurio-alimtalk', '', 20, 1),
('카카오톡 채널 개설하기', 'https://www.youtube.com/watch?v=57N893iKOp0', '57N893iKOp0', 'kakao-channel-open', '', 30, 1),
('카톡 비즈채널 개설 및 관리방법 Part1', 'https://www.youtube.com/watch?v=hn-f8H4CFh8', 'hn-f8H4CFh8', 'kakao-biz-part1', '', 40, 1),
('카톡 비즈채널 비즈니스 도구 Part2', 'https://www.youtube.com/watch?v=5un3zoTTcxM', '5un3zoTTcxM', 'kakao-biz-part2', '', 50, 1),
('카톡 비즈채널 캐러셀피드형 메세지 New', 'https://youtu.be/WpTRrKPcwnI', 'WpTRrKPcwnI', 'kakao-carousel', '', 60, 1),
('신협 테마이미지서비스로 이미지&동영상 만들기', 'https://www.youtube.com/watch?v=AXbCueVUxak', 'AXbCueVUxak', 'cu-theme-image', '', 70, 1),
('HTML 웹문서 생성&배포', 'https://www.youtube.com/watch?v=whtifyguoG4', 'whtifyguoG4', 'html-github', '', 80, 1),
('HTML 웹문서 ChatGPT로 코딩하기', 'https://www.youtube.com/watch?v=_Q2TQTrlFdc', '_Q2TQTrlFdc', 'html-chatgpt', '', 90, 1),
('App Script으로 설문지 만들기 with ChatGPT Part1', 'https://www.youtube.com/watch?v=aNQJ7lqXhDA', 'aNQJ7lqXhDA', 'gas-form-part1', '', 100, 1),
('App Script으로 설문지 만들기 with ChatGPT Part2', 'https://www.youtube.com/watch?v=t7cbeWwGMGg', 't7cbeWwGMGg', 'gas-form-part2', '', 110, 1),
('설문지 App 만들기 by Google AppSheet', 'https://www.youtube.com/watch?v=WMhK0hpIGLs', 'WMhK0hpIGLs', 'appsheet-form', '', 120, 1),
('깃허브 회원가입&레포지토리 생성하기', 'https://www.youtube.com/watch?v=4D0sJytJIEA', '4D0sJytJIEA', 'github-start', '', 130, 1),
('깃허브 데스크탑으로 웹문서 배포', 'https://www.youtube.com/watch?v=s7FY3K5b3DU', 's7FY3K5b3DU', 'github-desktop', '', 140, 1),
('[최신] 구글앱스크립트로 극장예매시스템 만들기', 'https://youtu.be/S5-O9cVfFEk', 'S5-O9cVfFEk', 'gas-movie-reservation', '', 150, 1)
ON DUPLICATE KEY UPDATE
  title=VALUES(title), youtube_url=VALUES(youtube_url), youtube_id=VALUES(youtube_id),
  description=VALUES(description), sort_order=VALUES(sort_order), is_active=VALUES(is_active);
