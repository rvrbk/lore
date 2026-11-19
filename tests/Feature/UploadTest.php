<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_images_and_documents_can_be_uploaded_and_are_served_privately(): void
    {
        Storage::fake('local');

        $image = $this->postJson(route('uploads.store'), ['file' => UploadedFile::fake()->createWithContent('My Screenshot.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))])->assertOk()->json();
        $this->assertTrue($image['is_image']);
        $this->assertMatchesRegularExpression('#/uploads/[a-z0-9]{10}-my-screenshot\.png$#', $image['url']);
        $this->get($image['url'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'inline; filename='.basename($image['url']));

        $doc = $this->postJson(route('uploads.store'), ['file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])->assertOk()->json();
        $this->assertFalse($doc['is_image']);

        $csv = $this->postJson(route('uploads.store'), ['file' => UploadedFile::fake()->createWithContent('data.csv', "a,b\n1,2\n")])->assertOk()->json();
        $this->get($csv['url'])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename='.basename($csv['url']));

        auth()->logout();
        $this->get($image['url'])->assertRedirect(route('login'));
    }

    public function test_scriptable_oversized_and_traversal_files_are_refused(): void
    {
        Storage::fake('local');

        foreach (['evil.html', 'evil.svg', 'evil.php', 'evil.js', 'evil.exe'] as $name) {
            $this->postJson(route('uploads.store'), ['file' => UploadedFile::fake()->createWithContent($name, '<script>alert(1)</script>')])->assertStatus(422);
        }
        $this->postJson(route('uploads.store'), ['file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')])->assertStatus(422);
        $this->postJson(route('uploads.store'), [])->assertStatus(422);

        Storage::disk('local')->put('secret.txt', 'private');
        $this->get('/uploads/..%2Fsecret.txt')->assertNotFound();
        $this->get('/uploads/does-not-exist.png')->assertNotFound();
    }
}
