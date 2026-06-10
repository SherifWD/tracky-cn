<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectSetting;
use App\Traits\HelpersTrait;

class ProjectSettingController extends Controller
{
    use HelpersTrait;

    public function whatsappNumber()
    {
        $whatsappNumber = ProjectSetting::query()->value('whatsapp_number');

        return $this->returnData('whatsapp_number', [
            'whatsapp_number' => $whatsappNumber,
        ], 'WhatsApp number returned');
    }
}
