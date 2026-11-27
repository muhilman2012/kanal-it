<?php

namespace Tests\Feature;

use App\Models\AccessRole;
use App\Models\LobbyItem;
use App\Models\LobbyScreen;
use App\Models\User;
use App\Services\LobbySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LobbyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaPath = storage_path('framework/testing/lobby-media-'.getmypid());
        config(['lobby.media_path' => $this->mediaPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaPath);
        parent::tearDown();
    }

    public function test_admin_can_create_screen_and_token_protects_player(): void
    {
        $this->get('/lobby')->assertRedirect('/login');
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/lobby/screens', ['name'=>'Lobby Utama','location'=>'Lantai 1','active'=>1])
            ->assertRedirect();
        $screen = LobbyScreen::firstOrFail();
        $this->assertSame(48, strlen($screen->token_encrypted));
        $this->assertNotSame($screen->token_encrypted, $screen->getRawOriginal('token_encrypted'));
        $this->get('/lobby')->assertOk()->assertSee('Lobby Utama');
        $this->get('/layar-lobby/'.$screen->token_encrypted)->assertOk()->assertSee('lobby-player.js');
        $this->getJson('/layar-lobby/'.$screen->token_encrypted.'/playlist')->assertOk()->assertJsonCount(0, 'items');
        $this->get('/layar-lobby/'.str_repeat('a',48))->assertNotFound();

        $oldToken = $screen->token_encrypted;
        $this->post('/lobby/screens/'.$screen->id.'/rotate')->assertRedirect();
        $screen->refresh();
        $this->assertNotSame($oldToken, $screen->token_encrypted);
        $this->get('/layar-lobby/'.$oldToken)->assertNotFound();
        $this->get('/layar-lobby/'.$screen->token_encrypted)->assertOk();
        $this->put('/lobby/screens/'.$screen->id, ['name'=>'Lobby Utama','active'=>0])->assertRedirect();
        $this->get('/layar-lobby/'.$screen->token_encrypted)->assertNotFound();
    }

    public function test_role_permission_controls_admin_but_not_tokenized_player(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/lobby/screens', ['name'=>'TV Lobby','active'=>1]);
        $screen = LobbyScreen::firstOrFail();
        $role = AccessRole::where('name', 'Pegawai')->firstOrFail();
        $member = User::factory()->create(['access_role_id'=>$role->id]);
        $this->actingAs($member)->get('/lobby')->assertForbidden();
        $this->get('/layar-lobby/'.$screen->token_encrypted)->assertOk();
    }

    public function test_image_upload_reorder_and_media_are_scoped_to_screen(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/lobby/screens', ['name'=>'TV A','active'=>1]);
        $this->post('/lobby/screens', ['name'=>'TV B','active'=>1]);
        [$first,$second] = LobbyScreen::orderBy('id')->get()->all();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6V2sAAAAASUVORK5CYII=');
        $this->post('/lobby/screens/'.$first->id.'/items', [
            'title'=>'Gambar awal','kind'=>'image','file'=>UploadedFile::fake()->createWithContent('slide.png',$png),
            'fit'=>'cover','duration_seconds'=>15,'mode'=>'always','active'=>1,
        ])->assertRedirect();
        $this->post('/lobby/screens/'.$first->id.'/items', [
            'title'=>'Website','kind'=>'website','website_url'=>'https://example.org/',
            'fit'=>'contain','duration_seconds'=>20,'mode'=>'always','active'=>1,
        ])->assertRedirect();
        $items = LobbyItem::orderBy('id')->get();
        $this->assertCount(2,$items);
        $this->assertFileExists($this->mediaPath.'/'.$items[0]->file_path);
        $playlist = $this->getJson('/layar-lobby/'.$first->token_encrypted.'/playlist')->assertOk()->json('items');
        $this->assertSame(['image','website'],array_column($playlist,'kind'));
        $this->assertSame('https://example.org/',$playlist[1]['url']);
        $this->get('/layar-lobby/'.$first->token_encrypted.'/media/'.$items[0]->id)->assertOk()->assertHeader('Content-Type','image/png');
        $this->get('/layar-lobby/'.$second->token_encrypted.'/media/'.$items[0]->id)->assertNotFound();
        $this->post('/lobby/screens/'.$first->id.'/items/'.$items[1]->id.'/move',['direction'=>'up'])->assertRedirect();
        $playlist = $this->getJson('/layar-lobby/'.$first->token_encrypted.'/playlist')->json('items');
        $this->assertSame(['website','image'],array_column($playlist,'kind'));
        $this->delete('/lobby/screens/'.$first->id.'/items/'.$items[0]->id)->assertRedirect();
        $this->assertFileDoesNotExist($this->mediaPath.'/'.$items[0]->file_path);
    }

    public function test_schedule_filters_weekdays_and_exclusive_takeover(): void
    {
        $always = new LobbyItem(['kind'=>'image','mode'=>'always','active'=>true,'sort_order'=>10]);
        $periodic = new LobbyItem([
            'kind'=>'website','mode'=>'scheduled','active'=>true,'exclusive'=>true,
            'sort_order'=>20,'weekdays'=>[1,4],'starts_at'=>'10:00','ends_at'=>'10:30',
        ]);
        $monday = CarbonImmutable::parse('2026-11-30 10:10:00','Asia/Jakarta');
        $this->assertSame([$periodic], LobbySchedule::playlist([$always,$periodic],$monday));
        $this->assertSame([$always], LobbySchedule::playlist([$always,$periodic],$monday->addHour()));
        $this->assertSame([$always], LobbySchedule::playlist([$always,$periodic],$monday->addDay()));
        $periodic->weekdays = [1];
        $periodic->starts_at = '22:00';
        $periodic->ends_at = '02:00';
        $this->assertSame([$periodic], LobbySchedule::playlist([$always,$periodic],$monday->addDay()->setTime(1,0)));
        $periodic->starts_on = '2026-12-02';
        $this->assertSame([$always], LobbySchedule::playlist([$always,$periodic],$monday->addDay()->setTime(1,0)));
    }

    public function test_invalid_website_and_wrong_media_type_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/lobby/screens', ['name'=>'TV A','active'=>1]);
        $screen = LobbyScreen::firstOrFail();
        $base = ['title'=>'Tayangan','fit'=>'contain','duration_seconds'=>15,'mode'=>'always','active'=>1];
        $this->post('/lobby/screens/'.$screen->id.'/items', $base + [
            'kind'=>'website','website_url'=>'http://example.org/',
        ])->assertSessionHasErrors('website_url');
        $this->post('/lobby/screens/'.$screen->id.'/items', $base + [
            'kind'=>'image','file'=>UploadedFile::fake()->createWithContent('dokumen.txt','bukan gambar'),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('lobby_items',0);
    }
}
