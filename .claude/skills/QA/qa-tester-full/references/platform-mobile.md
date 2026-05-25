# Mobile App Testing — Checklist & Strategy

Áp dụng cho Native (Swift/Kotlin), Cross-platform (React Native, Flutter), Hybrid (Ionic, Cordova).

---

## 1. Device & OS Coverage Matrix

### Nguyên tắc chọn device test:
1. Lấy **thống kê từ analytics dự án** (Google Play Console, App Store Connect, Firebase) — đừng test theo cảm tính.
2. Ưu tiên: **top 5 device** theo user + **min OS supported** + **latest OS**.
3. Cover cả **3 nhóm**: low-end (RAM ít, CPU yếu), mid-range, high-end.

### Ma trận tối thiểu:

| Platform | Device | OS | Mục đích |
|---|---|---|---|
| iOS | iPhone SE (small screen) | iOS min | Layout nhỏ + perf yếu |
| iOS | iPhone 13/14/15 | iOS latest | Mainstream |
| iOS | iPhone Pro Max | iOS latest | Large screen + Dynamic Island |
| iOS | iPad | iPadOS | Tablet layout |
| Android | Samsung Galaxy A series | Android min | Mid-range mainstream |
| Android | Pixel | Android latest | Reference Android |
| Android | Xiaomi/Oppo | Android phổ biến | Custom skin (MIUI, ColorOS) |
| Android | Tablet | Android | Layout |

**Custom skin Android** thường gây bug đặc thù (notification, permission UI, battery optimization khác AOSP).

---

## 2. Functional Testing — Khác biệt so với Web

### 2.1 Lifecycle của App

Bắt buộc test mọi feature ở các trạng thái sau:

- **Cold start**: app bị kill, mở lại từ đầu.
- **Warm start**: app trong background → foreground.
- **App killed by OS** (low memory): trở lại app — restore state đúng?
- **Force quit + relaunch**: data persistent đúng?
- **Multitasking**: chuyển sang app khác → quay lại — không crash, không mất data.

### 2.2 Permission

Test mọi permission cần xin:

- **Lần đầu request**: dialog hiển thị đúng, message giải thích rõ.
- **User deny**: app vẫn dùng được phần không cần permission đó; có hướng dẫn đến Settings.
- **User allow rồi tắt trong Settings**: app phát hiện và xử lý đúng (không crash).
- **iOS-specific**: permission "Allow Once" vs "Allow While Using App" vs "Don't Allow".
- **Android 13+**: notification permission cần xin riêng; granular media permission (Photos vs Videos).

Permission thường gặp: Camera, Microphone, Location, Photos, Contacts, Notification, Bluetooth, Calendar.

### 2.3 Network Conditions

App phải robust với mạng kém — bắt buộc test:

- **Offline**: mở app, navigate, submit form — UI đúng, có cache không, message rõ ràng.
- **Slow 3G** (Network Link Conditioner trên iOS, Charles/Proxyman): timeout đúng, có loading state, không freeze UI.
- **Mất mạng giữa chừng**: đang upload file → mất wifi → resume hay fail rõ?
- **Chuyển wifi → 4G**: session có giữ không, có request lặp không.
- **Airplane mode toggle**: app phục hồi đúng.

### 2.4 Push Notification

- Receive khi: foreground, background, app killed.
- Tap notification: deep link đúng vào màn hình tương ứng.
- Multiple notification: stack đúng, group đúng.
- Silent notification (data-only): xử lý đúng background task.
- Token refresh: đăng ký lại với server.
- User tắt notification trong Settings: app handle đúng.

### 2.5 Deep Linking & Universal/App Links

- URL schema (`myapp://...`): mở đúng màn hình.
- Universal Links (iOS) / App Links (Android): mở app khi đã cài, mở web khi chưa cài.
- Deep link với param: parse đúng.
- Deep link khi chưa login: điều hướng đến login → sau login về đúng màn hình đích.
- Deep link khi đã login khác account: xử lý logout-relogin hoặc thông báo phù hợp.

### 2.6 Gesture & Touch

- Tap, double tap, long press: action đúng.
- Swipe: left/right/up/down — không nhầm gesture.
- Pinch zoom: image, map.
- Pull to refresh.
- Swipe to delete (iOS), swipe action.
- Edge swipe (back gesture iOS, Android 10+).
- Touch target ≥ 44pt (iOS) / 48dp (Android).

### 2.7 Keyboard Behavior

- Tap input → keyboard show, không che field.
- Auto-scroll khi keyboard show.
- Done/Next/Search button trên keyboard.
- Multi-line input không bị mất khi xoay.
- Số → numeric keyboard, email → email keyboard.
- Tap outside → keyboard dismiss.

### 2.8 Orientation

- Portrait → Landscape: layout reflow, không mất state, không crash.
- Lock orientation cho màn hình specific (vd: video player landscape).
- Tablet thường support cả hai; phone có thể chỉ portrait.

---

## 3. Cross-Platform Specific Issues

### iOS-specific:
- **Safe area**: notch, Dynamic Island, home indicator — content không bị che.
- **Status bar**: light/dark phù hợp background.
- **3D Touch / Haptic Touch**: shortcut menu.
- **Face ID / Touch ID**: fallback đúng khi fail (passcode).
- **iCloud sync**: nếu app dùng.
- **Background fetch / Background task**: được gọi đúng.
- **App Tracking Transparency** (iOS 14.5+): xin permission đúng cách.

### Android-specific:
- **Hardware back button**: hành vi đúng (close modal, back stack, exit app).
- **Recent apps**: preview screenshot không lộ data nhạy cảm.
- **Battery optimization / Doze mode**: app vẫn chạy đúng khi user không tương tác.
- **Adaptive icon**: hiển thị đúng trên các launcher.
- **Different OEM customization**: Samsung One UI, MIUI, etc. có thể override behavior.
- **Edge-to-edge display**: insets đúng.

### Cross-platform (RN, Flutter):
- Nhất quán giữa iOS và Android (cùng feature, cùng UX) hay theo native pattern (mỗi platform 1 pattern)?
- Native module nếu có: test trên cả 2.
- Hot reload không reflect bug — test trên build production.

---

## 4. App Store / Google Play Specific

- **App size**: < 100MB (iOS App Store cellular limit), < 150MB (Play Store optional).
- **Install/Update flow**: install fresh, update từ version cũ — migration data đúng.
- **Permissions trong manifest**: chỉ xin những gì thật sự cần (App Store reject nếu thừa).
- **Privacy Manifest** (iOS 17+): khai báo đúng API usage và data collection.
- **In-App Purchase**: sandbox test, restore purchase, refund handling, subscription renewal.
- **App Review Guidelines**: test các kịch bản reviewer thường check (đăng nhập demo, thanh toán test).

---

## 5. Performance Testing trên Mobile

- **Cold start time**: < 2s với cold start (best practice Google).
- **Frame rate**: 60fps cho animation; check bằng Xcode Instruments / Android Profiler.
- **Memory**: không leak khi navigate qua lại nhiều màn hình.
- **Battery drain**: dùng app 30 phút — % pin tiêu thụ hợp lý.
- **CPU/GPU usage**: idle không > 5%; active không kéo dài > 30%.
- **App size on disk**: tăng dần qua usage (cache) phải có giới hạn và clear cache option.
- **Network usage**: không gọi API thừa, dùng cache hợp lý, đo bằng Charles/Proxyman.

---

## 6. Security Testing trên Mobile

- **Local storage**: data nhạy cảm có encrypted? (iOS Keychain, Android Keystore — không phải SharedPreferences/UserDefaults plaintext).
- **API key trong code**: dùng `strings` (binary) để check không có API key hardcoded.
- **Certificate pinning**: test bằng Charles/mitmproxy — nếu MITM được thì pinning fail.
- **Jailbreak/Root detection**: app banking nên detect và cảnh báo/từ chối.
- **Screen recording / Screenshot**: màn hình nhạy cảm (OTP, password) có chặn không.
- **Background screen**: khi switch app, screenshot iOS có ẩn data không (privacy screen).
- **Deep link validation**: deep link không trust input, validate trước khi action.
- **WebView**: nếu có, không enable JS bừa bãi, không load URL từ external untrusted.

---

## 7. Accessibility Mobile

### iOS — VoiceOver
- Mọi element có `accessibilityLabel`.
- Custom component có `accessibilityTraits` đúng.
- Dynamic Type: text scale theo system setting (Settings → Display → Text Size).
- Reduce Motion: respect setting.

### Android — TalkBack
- `contentDescription` cho image, icon button.
- `android:importantForAccessibility` cho decorative.
- Font scale up to 200%.
- Color contrast theo WCAG.

### Common:
- Touch target size đủ lớn.
- Không dựa vào màu sắc alone.
- Caption cho video.

---

## 8. Tools đề xuất cho Mobile testing

| Loại | iOS | Android | Cross |
|---|---|---|---|
| E2E automation | XCUITest | Espresso | Appium, Detox (RN), Maestro |
| Performance | Xcode Instruments | Android Profiler | Firebase Performance |
| Crash report | Crashlytics, Sentry | Crashlytics, Sentry | — |
| Device farm | TestFlight + real | Play Console internal testing | Firebase Test Lab, BrowserStack App Live, Sauce Labs |
| Network | Charles, Proxyman | Charles, mitmproxy | — |
| Accessibility | Accessibility Inspector | Accessibility Scanner | — |
| Security | MobSF, Frida | MobSF, Frida | — |

---

## 9. Pre-release Checklist (App Store / Play Store)

- [ ] Test trên min OS supported + latest OS
- [ ] Test trên small screen device (iPhone SE, smallest Android)
- [ ] Test offline + slow network
- [ ] Test permission deny path
- [ ] Test push notification all 3 states
- [ ] Test deep link from cold/warm
- [ ] Test login + logout + session expired
- [ ] Test in-app purchase sandbox (nếu có)
- [ ] Test upgrade path từ version cũ (data migration)
- [ ] App size hợp lý
- [ ] Crash-free rate > 99.5% từ beta
- [ ] Accessibility với VoiceOver/TalkBack
- [ ] Privacy policy URL hoạt động
- [ ] App Store / Play Store metadata, screenshot đúng
