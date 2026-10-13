<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_store_a_private_profile_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => UploadedFile::fake()->image('perfil.png', 240, 240),
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('manual', $user->profile_photo_source);
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('local')->assertExists($user->profile_photo_path);

        $this->actingAs($user)->get('/profile/photo')->assertOk();
        $this->actingAs(User::factory()->create())->get('/profile/photo')->assertNotFound();
    }

    public function test_approved_didit_selfie_is_copied_to_private_profile_storage_for_a_model(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://didit.test/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLJ6wAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png']),
        ]);
        $user = User::factory()->create();
        $lead = Lead::create([
            'code' => 'VEL-LEAD-PHOTO',
            'status' => 'CONVERTED',
            'candidate_type' => 'MODEL',
            'first_name' => 'Laura',
            'last_name' => 'Foto',
            'sex' => 'WOMAN',
            'phone' => '3000000000',
            'email' => $user->email,
            'country' => 'Colombia',
            'city' => 'Bogota',
            'birth_date' => '1998-01-01',
            'availability' => 'Tiempo completo',
            'work_mode' => 'En estudio',
        ]);
        Candidate::create([
            'code' => 'VEL-CAN-PHOTO',
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'candidate_type' => 'MODEL',
            'status' => 'ACTIVE',
            'identity_verification_status' => 'APPROVED',
            'identity_verification_data' => ['images' => [['label' => 'Selfie', 'url' => 'https://didit.test/selfie.png']]],
        ]);

        $this->actingAs($user)->get('/profile')->assertOk();

        $user->refresh();
        $this->assertSame('didit', $user->profile_photo_source);
        Storage::disk('local')->assertExists($user->profile_photo_path);
        $this->actingAs($user)->get('/profile/photo')->assertOk();
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
