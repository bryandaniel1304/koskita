import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Kunci tanda tangan RILIS (upload key Play Store) -- dibaca dari
// android/key.properties yang TIDAK ikut di-commit (lihat .gitignore).
// Cara membuatnya ada di rilis-playstore/PANDUAN-PLAYSTORE.md langkah 3.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

android {
    // namespace = paket kode Kotlin (MainActivity, KosWidgetProvider) --
    // sengaja dibiarkan sama dengan app skripsi; yang dilihat Play Store
    // dan pengguna adalah applicationId di bawah.
    namespace = "com.koskita.frontend"
    compileSdk = 36
    ndkVersion = "28.2.13676358"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Dibutuhkan flutter_local_notifications (pengingat sewa lokal).
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // ID aplikasi di Play Store -- PERMANEN setelah upload pertama,
        // tidak bisa diganti lagi. Sengaja beda dari app skripsi
        // (com.koskita.frontend) supaya keduanya bisa terpasang di HP yang
        // sama tanpa saling menimpa.
        applicationId = "com.koskita.app"
        minSdk = flutter.minSdkVersion
        // Google Play mewajibkan target API terbaru untuk aplikasi baru.
        targetSdk = 36
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        create("release") {
            if (keystorePropertiesFile.exists()) {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            if (!keystorePropertiesFile.exists()) {
                throw GradleException(
                    "android/key.properties belum ada -- build rilis Play Store wajib " +
                        "ditandatangani upload key. Lihat rilis-playstore/PANDUAN-PLAYSTORE.md langkah 3."
                )
            }
            signingConfig = signingConfigs.getByName("release")
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
