<?php

namespace App\Http\Controllers\Api\v1\Ocr;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Aliyun\OcrService;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    /** 身份证识别 */
    public function recognizeIdcard(Request $request, OcrService $service)
    {
        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        $result = $service->recognizeIdcard($validated['image']);

        if (!$result) {
            return ApiResponse::error('身份证识别失败');
        }

        return ApiResponse::success($result);
    }

    /** 营业执照识别 */
    public function recognizeBusinessLicense(Request $request, OcrService $service)
    {
        $validated = $request->validate([
            'image' => 'required|string',
        ]);

        $result = $service->recognizeBusinessLicense($validated['image']);

        if (!$result) {
            return ApiResponse::error('营业执照识别失败');
        }

        return ApiResponse::success($result);
    }
}
