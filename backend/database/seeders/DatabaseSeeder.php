<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Kos;
use App\Models\Facility;
use App\Models\Rule;
use App\Models\UserInteraction;
use App\Models\Booking;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Master Facilities
        $facilitiesData = ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'];
        $facilities = [];
        foreach ($facilitiesData as $name) {
            $facilities[$name] = Facility::create(['name' => $name]);
        }

        // 2. Seed Master Rules
        $rulesData = ['Jam Malam', 'Tamu Boleh Menginap', 'Bawa Hewan', 'Merokok'];
        $rules = [];
        foreach ($rulesData as $name) {
            $rules[$name] = Rule::create(['name' => $name]);
        }

        // 3. Seed 15 Kos Listings in Tangerang (Karawaci, BSD, Serpong)
        $kosesData = [
            [
                'name' => 'CIU Residence Karawaci',
                'price' => 3200000,
                'gender_type' => 'campur',
                'location' => 'Karawaci',
                'distance_to_campus' => 1.2,
                'description' => 'Kamar single luas 18m2. Fasilitas lengkap, nyaman, dekat dengan kampus UPH. Minimal stay 2 bulan.',
                'image_url' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir'],
                'rules' => ['Jam Malam', 'Tamu Boleh Menginap']
            ],
            [
                'name' => 'Kost Orange Serpong',
                'price' => 1800000,
                'gender_type' => 'putra',
                'location' => 'Serpong',
                'distance_to_campus' => 2.5,
                'description' => 'Kost khusus putra di area Gading Serpong. Dekat UMN, tenang, dan bersih.',
                'image_url' => 'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'Parkir', 'Laundry'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'D\'Griya BSD',
                'price' => 2500000,
                'gender_type' => 'putri',
                'location' => 'BSD',
                'distance_to_campus' => 0.8,
                'description' => 'Kost eksklusif putri di BSD. Dekat dengan kampus Prasetiya Mulya dan AEON Mall.',
                'image_url' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Parkir', 'Laundry'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Edelweiss Karawaci',
                'price' => 1500000,
                'gender_type' => 'putri',
                'location' => 'Karawaci',
                'distance_to_campus' => 1.5,
                'description' => 'Kost putri ekonomis, lingkungan aman dan kekeluargaan.',
                'image_url' => 'https://images.unsplash.com/photo-1554995207-c18c203602cb?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['WiFi', 'KM Dalam', 'Dapur'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Putra Garuda Serpong',
                'price' => 1200000,
                'gender_type' => 'putra',
                'location' => 'Serpong',
                'distance_to_campus' => 3.2,
                'description' => 'Kost putra murah meriah dengan fasilitas standar, cocok untuk mahasiswa/pekerja.',
                'image_url' => 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['WiFi', 'Parkir'],
                'rules' => ['Merokok']
            ],
            [
                'name' => 'Kost Cozy Stay BSD',
                'price' => 4000000,
                'gender_type' => 'campur',
                'location' => 'BSD',
                'distance_to_campus' => 1.0,
                'description' => 'Apartment-style kost dengan kamar mandi dalam, air panas, AC, TV kabel, dan parkir luas.',
                'image_url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'],
                'rules' => ['Tamu Boleh Menginap', 'Bawa Hewan']
            ],
            [
                'name' => 'Kost Green View Karawaci',
                'price' => 2000000,
                'gender_type' => 'campur',
                'location' => 'Karawaci',
                'distance_to_campus' => 2.0,
                'description' => 'Kost dengan pemandangan hijau dan asri, parkir motor & mobil tersedia.',
                'image_url' => 'https://images.unsplash.com/photo-1598928636135-d146006ff4be?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Parkir'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Putri Lavender BSD',
                'price' => 2200000,
                'gender_type' => 'putri',
                'location' => 'BSD',
                'distance_to_campus' => 1.7,
                'description' => 'Kost khusus putri yang nyaman, bersih, dengan keamanan 24 jam.',
                'image_url' => 'https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Laundry'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Sunrise Serpong',
                'price' => 1600000,
                'gender_type' => 'campur',
                'location' => 'Serpong',
                'distance_to_campus' => 2.1,
                'description' => 'Lokasi strategis di Serpong, dekat pusat perbelanjaan dan kuliner.',
                'image_url' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'Dapur', 'Parkir'],
                'rules' => ['Jam Malam', 'Merokok']
            ],
            [
                'name' => 'Kost U-Residence Karawaci',
                'price' => 4500000,
                'gender_type' => 'campur',
                'location' => 'Karawaci',
                'distance_to_campus' => 0.5,
                'description' => 'Premium studio room langsung terkoneksi ke Supermall Karawaci. Pilihan terbaik mahasiswa UPH.',
                'image_url' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'],
                'rules' => ['Tamu Boleh Menginap']
            ],
            [
                'name' => 'Kost Bintang Serpong',
                'price' => 1400000,
                'gender_type' => 'putra',
                'location' => 'Serpong',
                'distance_to_campus' => 1.8,
                'description' => 'Kost putra sederhana dekat akses jalan raya utama.',
                'image_url' => 'https://images.unsplash.com/photo-1556020685-ae41abfc9365?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'Parkir'],
                'rules' => ['Merokok']
            ],
            [
                'name' => 'Kost Sakura BSD',
                'price' => 2800000,
                'gender_type' => 'putri',
                'location' => 'BSD',
                'distance_to_campus' => 1.2,
                'description' => 'Kost putri dengan desain modern ala Jepang, bersih dan rapi.',
                'image_url' => 'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Laundry'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Executive Serpong',
                'price' => 3500000,
                'gender_type' => 'campur',
                'location' => 'Serpong',
                'distance_to_campus' => 1.5,
                'description' => 'Kost mewah untuk pekerja kantor dan eksekutif muda di BSD/Serpong.',
                'image_url' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'],
                'rules' => ['Jam Malam', 'Tamu Boleh Menginap']
            ],
            [
                'name' => 'Kost Cempaka Karawaci',
                'price' => 1100000,
                'gender_type' => 'putri',
                'location' => 'Karawaci',
                'distance_to_campus' => 2.8,
                'description' => 'Kost putri ekonomis, kamar non-AC tetapi sejuk dan ventilasi bagus.',
                'image_url' => 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['WiFi', 'Dapur', 'Parkir'],
                'rules' => ['Jam Malam']
            ],
            [
                'name' => 'Kost Pinnacle BSD',
                'price' => 3800000,
                'gender_type' => 'campur',
                'location' => 'BSD',
                'distance_to_campus' => 0.9,
                'description' => 'Kost premium dengan smart door lock, cctv, parkir luas, dan lounge bersama.',
                'image_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=500&q=80',
                'facilities' => ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'],
                'rules' => ['Tamu Boleh Menginap', 'Bawa Hewan']
            ]
        ];

        $koses = [];
        foreach ($kosesData as $data) {
            $kos = Kos::create([
                'name' => $data['name'],
                'price' => $data['price'],
                'gender_type' => $data['gender_type'],
                'location' => $data['location'],
                'distance_to_campus' => $data['distance_to_campus'],
                'description' => $data['description'],
                'image_url' => $data['image_url'],
            ]);

            // Sync facilities
            $facilityIds = [];
            foreach ($data['facilities'] as $fName) {
                if (isset($facilities[$fName])) {
                    $facilityIds[] = $facilities[$fName]->id;
                }
            }
            $kos->facilities()->sync($facilityIds);

            // Sync rules
            $ruleIds = [];
            foreach ($data['rules'] as $rName) {
                if (isset($rules[$rName])) {
                    $ruleIds[] = $rules[$rName]->id;
                }
            }
            $kos->rules()->sync($ruleIds);

            $koses[] = $kos;
        }

        // 4. Seed Admin User
        User::create([
            'name' => 'Bryan Admin',
            'email' => 'admin@koskita.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        // Seed 1 Demo User (to test cold-start / onboarding easily)
        $demoUser = User::create([
            'name' => 'Bryan Daniel',
            'email' => 'bryan@koskita.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
        // Do NOT create profile for demoUser yet, we want them to do it in onboarding! Or we can create an empty profile or let the frontend trigger it.

        // 5. Seed 150 Simulated Users with Profiles & Interactions (Ratings)
        $occupations = ['mahasiswa', 'pekerja'];
        $genders = ['pria', 'wanita'];
        $locations = ['Karawaci', 'BSD', 'Serpong'];
        $facilityNames = ['AC', 'WiFi', 'KM Dalam', 'Dapur', 'Parkir', 'Laundry'];
        $ruleNames = ['Jam Malam', 'Tamu Boleh Menginap', 'Bawa Hewan', 'Merokok'];

        for ($i = 1; $i <= 150; $i++) {
            $user = User::create([
                'name' => 'Responden ' . $i,
                'email' => 'user' . $i . '@example.com',
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]);

            $gender = $genders[array_rand($genders)];
            $occupation = $occupations[array_rand($occupations)];
            $preferredLoc = $locations[array_rand($locations)];
            
            // Random budget
            $budgetMin = rand(10, 20) * 100000; // 1M - 2M
            $budgetMax = rand(25, 50) * 100000; // 2.5M - 5M

            // Random preferred facilities (2 to 4 facilities)
            $prefFac = [];
            $facKeys = (array) array_rand($facilityNames, rand(2, 4));
            foreach ($facKeys as $k) {
                $prefFac[] = $facilityNames[$k];
            }

            // Random preferred rules (1 to 2 rules)
            $prefRules = [];
            $ruleKeys = (array) array_rand($ruleNames, rand(1, 2));
            foreach ($ruleKeys as $k) {
                $prefRules[] = $ruleNames[$k];
            }

            UserProfile::create([
                'user_id' => $user->id,
                'gender' => $gender,
                'occupation' => $occupation,
                'budget_min' => $budgetMin,
                'budget_max' => $budgetMax,
                'preferred_facilities' => $prefFac,
                'preferred_rules' => $prefRules,
                'preferred_location' => $preferredLoc,
            ]);

            // Seed interactions / ratings
            // Rate 3-6 random koses
            $ratedKosKeys = (array) array_rand($koses, rand(3, 6));
            foreach ($ratedKosKeys as $key) {
                $kos = $koses[$key];

                // Heuristics for ratings (to build a realistic system)
                $rating = 3; // Default moderate rating

                // Hard gender constraint check
                if (($kos->gender_type == 'putra' && $gender == 'wanita') ||
                    ($kos->gender_type == 'putri' && $gender == 'pria')) {
                    $rating = rand(1, 2); // very low rating if gender mismatch
                } else {
                    // Check budget match
                    $budgetMatch = ($kos->price >= $budgetMin && $kos->price <= $budgetMax);
                    // Check location match
                    $locationMatch = ($kos->location == $preferredLoc);

                    if ($budgetMatch && $locationMatch) {
                        $rating = rand(4, 5);
                    } elseif ($budgetMatch || $locationMatch) {
                        $rating = rand(3, 4);
                    } else {
                        $rating = rand(2, 3);
                    }
                }

                UserInteraction::create([
                    'user_id' => $user->id,
                    'kos_id' => $kos->id,
                    'rating' => $rating,
                    'is_favorite' => ($rating >= 4) ? (rand(0, 1) == 1) : false,
                    'click_count' => rand(1, 5),
                ]);
            }
        }

        // 6. Seed contoh booking dengan berbagai status untuk demo halaman admin
        $sampleUsers = User::where('role', 'user')->where('id', '!=', $demoUser->id)->inRandomOrder()->take(3)->get();
        $bookingStatuses = ['pending', 'confirmed', 'rejected'];
        foreach ($sampleUsers as $index => $bookingUser) {
            Booking::create([
                'user_id' => $bookingUser->id,
                'kos_id' => $koses[array_rand($koses)]->id,
                'start_date' => now()->addDays(rand(3, 30)),
                'duration_months' => rand(1, 6),
                'notes' => 'Booking contoh untuk demo admin.',
                'status' => $bookingStatuses[$index % count($bookingStatuses)],
            ]);
        }
    }
}
