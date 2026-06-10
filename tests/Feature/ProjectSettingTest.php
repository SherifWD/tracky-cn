<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectSettingResource;
use App\Models\ProjectSetting;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('project_settings');
        Schema::create('project_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('singleton')->default(true)->unique();
            $table->string('whatsapp_number')->nullable();
            $table->timestamps();
        });
    }

    public function test_whatsapp_number_api_returns_null_when_settings_do_not_exist(): void
    {
        $response = $this->getJson('/api/settings/whatsapp-number');

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('msg', 'WhatsApp number returned')
            ->assertJsonPath('data.whatsapp_number', null);
    }

    public function test_whatsapp_number_api_returns_configured_number(): void
    {
        ProjectSetting::create([
            'whatsapp_number' => '+201001112223',
        ]);

        $response = $this->getJson('/api/settings/whatsapp-number');

        $response
            ->assertOk()
            ->assertJsonPath('result', true)
            ->assertJsonPath('data.whatsapp_number', '+201001112223');
    }

    public function test_project_settings_are_limited_to_one_row(): void
    {
        $this->assertTrue(ProjectSettingResource::canCreate());

        ProjectSetting::create([
            'whatsapp_number' => '+201001112223',
        ]);

        $this->assertFalse(ProjectSettingResource::canCreate());
        $this->expectException(QueryException::class);

        ProjectSetting::create([
            'whatsapp_number' => '+201004445556',
        ]);
    }
}
