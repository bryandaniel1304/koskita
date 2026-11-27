<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\FcmToken;
use App\Models\Message;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('fcm_access_token');
    }

    /**
     * Kunci RSA test-only (bukan kunci sungguhan siapa pun -- di-generate
     * sekali lewat `openssl genrsa` khusus buat fixture ini) supaya
     * openssl_sign() di FcmService::buildSignedJwt() benar-benar
     * menandatangani sesuatu yang valid, bukan dummy string. SENGAJA
     * ditulis statis di sini (bukan openssl_pkey_new() saat test jalan)
     * karena generate key baru butuh openssl.cnf yang tidak selalu ada di
     * environment PHP CLI standalone (mis. Windows tanpa OpenSSL penuh) --
     * openssl_sign() sendiri tidak butuh config itu, cuma pembuatan
     * key barunya yang butuh.
     */
    private const FAKE_PRIVATE_KEY_PEM = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDiKOTPMAIABZ1Y
        d8hmCa31A0UhRvS+Z9i3KemwwnNT7cCPWI4RlOUQhYVqiHjcQ+DHRZhKg8Qf8QE9
        twGdV6DVpnIzItlC9Q5fA1x96isSaSonJK6DkGoDNxResqN8fyBnagO+b5Dc/WIQ
        aCEekuHCo6ZHakihru61Qybn4NSd02UAxvWbWl6IvG5l5gMp/TlPDfUL8jbO41Z7
        nS952AOfuqFHBwQOsh9s7PlH2T9X2jm1jb624GVKlc2Q0by8BALbLmuGeZEHGOp9
        A46UxHiHyvGlJlfBQn7unmVrIJ8VkzGstEV948HWbYyznCF//Wt0zr0cE+3diiR0
        RQ0KFx7HAgMBAAECggEAGHzFCYnxLX9uIf4WPLYfl7/a5FeCeHtWA78OBo2HXzcG
        +n5kI5Mzmi5a28YbD/5pgCoQ60CJI8w8jioaiqbKS1fSVacYTx+phrAee6O3Ni8c
        2VAndSdV0zNLiVVeTkSVhZz8+smprcPhslUUAPN2blS51FG1u4vtXMMAm2rEmrHs
        tRladPmPry8LKGUyVJH6w0xbO4mGUTb2cjMGNDJrlupVKfbtopRGIREZ1voT5L8l
        ++XkNh4wrdFk790/9mcZnC6+0D2s3XkgvVPIhZ03LVKaSvkKIlq9twvodcfngomW
        OWBNClf4WBS70y5g9dp4B5kreHSVeG+bUv7oe5zzAQKBgQD+pEleNmUUI9c4l/tO
        uIEinz/HdT5OgJAQ2GDw5hDq7jQgaEc357hDngbHgMQGMuMk/nTe56Fs3tILGuMv
        w2dfQSymX8CIk2qITg/AnwpTN29DeabfuLVzErGmkPxjEUAmuJU3HvGUBkad+/do
        048BBDnG/V0MdTDTvYb3UxCKNwKBgQDjXbcKnIP8cRAhC2DwH1zxQ+VR9TJAz7jg
        St3adN+i5BuRvkbiCFBqxIlx2NXp0XI4ZLPbDA6G2fCvFANWSpyje7CPy1vqUpAk
        w7ayM9xRYVJ1ZoseJvfUq2ETEPcFF581j7yyNTR2irOihsnNlvNBF+8vyLSle8qa
        yydNnW6H8QKBgCGn6SKQoe91hT6vp8GR1U+UKMPeFSwfBeuUDcwJPHcwoKcf4Tnc
        YJhfTJoVxNLk6uy6zQuhJc7T7IMXPKvVcdY/MP2Ubkge49e8KYzV+HFjREtwysOL
        EEBzWhOf2hvl7cqwXth4OInOAotjACJUw/PocRKB7kh1PMzfofUSf38lAoGBAIRK
        Xto5v0H+txfC+yA732Qx0Rgixp6XPkaiyPr2zbiFaNUgFTYnCENXH3GybKSjTQYq
        8csd9MXZwQTdbCOlPgaRTWYdONnaOCxsA70kF6jyK3xLsd5VZhXDsbPaRyAbYnNT
        ssMt2dyvT13dd0W78sWJG45+BT3UDUqLsc6jL1DBAoGBAMkxIWTwxGhay1Z2r22v
        y8sQu1TDIcaqZGUfP9BfvDFYuAN99MppKmYUdNbtZoYJz81yQeN4Z9CplLGk653R
        gZODCWczlf2LI1lvruQHAOPivRcvjods27TOv3vs1snlI1K5v2YEJO3V/VRr4/9d
        19nZ5MLl7zYwX5Z7TuyIyUze
        -----END PRIVATE KEY-----
        PEM;

    private function fakeFirebaseCredentials(): void
    {
        config([
            'services.fcm.project_id' => 'koskita-test',
            'services.fcm.client_email' => 'test@koskita-test.iam.gserviceaccount.com',
            'services.fcm.private_key' => str_replace("\n", '\\n', self::FAKE_PRIVATE_KEY_PEM),
        ]);
    }

    public function test_fcm_service_reports_not_configured_when_credentials_are_empty(): void
    {
        config(['services.fcm.project_id' => null, 'services.fcm.client_email' => null, 'services.fcm.private_key' => null]);

        $this->assertFalse(FcmService::configured());
    }

    public function test_send_to_user_is_a_silent_no_op_when_not_configured(): void
    {
        config(['services.fcm.project_id' => null]);
        Http::fake();
        $user = User::factory()->create();
        FcmToken::factory()->for($user)->create();

        FcmService::sendToUser($user, 'Judul', 'Isi');

        Http::assertNothingSent();
    }

    public function test_send_to_user_is_a_silent_no_op_when_user_has_no_registered_devices(): void
    {
        $this->fakeFirebaseCredentials();
        Http::fake();
        $user = User::factory()->create();

        FcmService::sendToUser($user, 'Judul', 'Isi');

        Http::assertNothingSent();
    }

    public function test_send_to_user_mints_an_access_token_then_sends_to_every_registered_device(): void
    {
        $this->fakeFirebaseCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/koskita-test/messages/1']),
        ]);
        $user = User::factory()->create();
        FcmToken::factory()->for($user)->create(['token' => 'device-token-1']);
        FcmToken::factory()->for($user)->create(['token' => 'device-token-2']);

        FcmService::sendToUser($user, 'Pesan Baru', 'Halo!', ['type' => 'message']);

        Http::assertSentCount(3); // 1 OAuth2 token exchange + 2 pengiriman (2 perangkat)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && $request['message']['token'] === 'device-token-1'
                && $request['message']['notification']['title'] === 'Pesan Baru'
                && $request->hasHeader('Authorization', 'Bearer fake-access-token');
        });
    }

    public function test_access_token_is_cached_and_not_re_minted_for_a_second_send(): void
    {
        $this->fakeFirebaseCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
        $user = User::factory()->create();
        FcmToken::factory()->for($user)->create();

        FcmService::sendToUser($user, 'Judul 1', 'Isi 1');
        FcmService::sendToUser($user, 'Judul 2', 'Isi 2');

        Http::assertSentCount(3); // 1 token exchange (dipakai ulang) + 2 pengiriman
    }

    public function test_an_invalid_token_is_deleted_after_fcm_reports_it_as_unregistered(): void
    {
        $this->fakeFirebaseCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);
        $user = User::factory()->create();
        $deadToken = FcmToken::factory()->for($user)->create(['token' => 'dead-token']);

        FcmService::sendToUser($user, 'Judul', 'Isi');

        $this->assertDatabaseMissing('fcm_tokens', ['id' => $deadToken->id]);
    }

    public function test_registering_a_device_token_upserts_by_token_and_reassigns_ownership(): void
    {
        $oldOwner = User::factory()->create();
        FcmToken::factory()->for($oldOwner)->create(['token' => 'shared-device-token']);
        $newOwner = User::factory()->create();

        $response = $this->actingAs($newOwner, 'sanctum')->postJson('/api/fcm-token', [
            'token' => 'shared-device-token',
            'device_type' => 'android',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('fcm_tokens', 1);
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'shared-device-token', 'user_id' => $newOwner->id]);
    }

    public function test_deleting_a_device_token_only_removes_the_callers_own_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        FcmToken::factory()->for($user)->create(['token' => 'my-token']);
        FcmToken::factory()->for($other)->create(['token' => 'other-token']);

        $response = $this->actingAs($user, 'sanctum')->deleteJson('/api/fcm-token', ['token' => 'other-token']);

        $response->assertOk();
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'other-token']); // tidak terhapus, bukan milik $user
    }

    public function test_sending_a_message_triggers_a_push_via_the_fcm_channel(): void
    {
        Event::fake([MessageSent::class]); // broadcast Reverb tidak relevan di tes ini
        $this->fakeFirebaseCredentials();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
        $owner = User::factory()->create(['role' => 'owner']);
        $tenant = User::factory()->create(['role' => 'user']);
        FcmToken::factory()->for($owner)->create();

        $this->actingAs($tenant, 'sanctum')->postJson('/api/messages', [
            'receiver_id' => $owner->id,
            'body' => 'Masih kosong kah?',
        ])->assertCreated();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fcm.googleapis.com'));
    }
}
