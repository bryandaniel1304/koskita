plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.koskita.frontend"
    compileSdk = 36
    ndkVersion = "28.2.13676358"
    buildToolsVersion = "34.0.0"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Dibutuhkan flutter_local_notifications (pengingat sewa lokal) --
        // paket itu memakai API Java 8+ (java.time dkk.) yang di Android
        // API < 26 butuh "didesugar" dulu supaya kompatibel.
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "com.koskita.frontend"
        minSdk = flutter.minSdkVersion
        targetSdk = 34
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    // Pasangan dari isCoreLibraryDesugaringEnabled di atas.
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
