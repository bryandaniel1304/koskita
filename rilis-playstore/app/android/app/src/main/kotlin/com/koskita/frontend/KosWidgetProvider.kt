package com.koskita.frontend

import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.SharedPreferences
import android.net.Uri
import android.widget.RemoteViews
import es.antonborri.home_widget.HomeWidgetLaunchIntent
import es.antonborri.home_widget.HomeWidgetProvider

// Widget layar utama "Kos Terakhir Dilihat" -- nampilin kos yang paling
// baru dibuka pengguna di app (data-nya di-push dari Flutter lewat
// HomeWidget.saveWidgetData/updateWidget, lihat KosProvider). Tap widget
// langsung buka app ke halaman detail kos itu lewat deep link kustom
// "koskita://kos/{id}" -- pola yang sama dipakai DeepLinkService buat
// tautan dari luar app, jadi tidak perlu jalur navigasi terpisah.
class KosWidgetProvider : HomeWidgetProvider() {
    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray,
        widgetData: SharedPreferences,
    ) {
        appWidgetIds.forEach { widgetId ->
            val views = RemoteViews(context.packageName, R.layout.kos_widget)

            val kosId = widgetData.getInt("kos_id", -1)
            val kosName = widgetData.getString("kos_name", null)
            val kosPrice = widgetData.getString("kos_price", null)

            if (kosId >= 0 && kosName != null) {
                views.setTextViewText(R.id.widget_kos_name, kosName)
                views.setTextViewText(R.id.widget_kos_price, kosPrice ?: "")
                val uri = Uri.parse("koskita://kos/$kosId")
                val pendingIntent = HomeWidgetLaunchIntent.getActivity(context, MainActivity::class.java, uri)
                views.setOnClickPendingIntent(R.id.widget_root, pendingIntent)
            } else {
                // Belum ada riwayat -- tampilkan placeholder, tap cukup buka
                // app ke beranda (tanpa URI khusus) daripada tidak merespons.
                views.setTextViewText(R.id.widget_kos_name, "Belum ada kos yang dilihat")
                views.setTextViewText(R.id.widget_kos_price, "Buka app buat mulai jelajahi kos")
                val pendingIntent = HomeWidgetLaunchIntent.getActivity(context, MainActivity::class.java, null)
                views.setOnClickPendingIntent(R.id.widget_root, pendingIntent)
            }

            appWidgetManager.updateAppWidget(widgetId, views)
        }
    }
}
