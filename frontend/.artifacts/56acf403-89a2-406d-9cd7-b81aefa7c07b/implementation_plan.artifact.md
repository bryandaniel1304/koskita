# Upgrade Android Build Dependencies

This plan addresses the warnings regarding outdated versions of Gradle, Android Gradle Plugin (AGP), and Kotlin in the Flutter project's Android configuration.

## Proposed Changes

### [Component Name] Android Build Configuration

#### [MODIFY] [gradle-wrapper.properties](file:///D:/KosKita/frontend/android/gradle/wrapper/gradle-wrapper.properties)
- Upgrade Gradle version from `8.12` to `8.14`.

#### [MODIFY] [settings.gradle.kts](file:///D:/KosKita/frontend/android/android/settings.gradle.kts)
- Upgrade Android Gradle Plugin (AGP) from `8.9.1` to `8.11.1`.
- Upgrade Kotlin version from `2.1.0` to `2.2.20`.

## Verification Plan

### Automated Tests
- Run `flutter analyze` to ensure no new Dart issues are introduced (unlikely).
- Attempt a build or sync to verify the new versions are accepted. Since I cannot fully "build" and "deploy" in this environment without a device, I will check if `flutter doctor` or a simple build command starts without these specific version warnings.

### Manual Verification
- None required beyond ensuring the warnings are gone from the build logs.
