package com.koskita.frontend

import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity (bukan FlutterActivity) -- WAJIB untuk plugin
// local_auth (login sidik jari/wajah) di Android, dialog biometrik bawaan
// sistem butuh FragmentActivity sebagai host-nya.
class MainActivity : FlutterFragmentActivity()
