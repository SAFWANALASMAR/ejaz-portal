<?php

namespace App\Actions\Assistant;

use App\Models\AssistantSettings;
use App\Models\User;
use App\Services\Assistant\AssistantProviderException;
use App\Services\Assistant\AssistantProviders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * «اختبار الاتصال» في إعدادات المساعد: طلب قصير إلى المزود بالقيم المعروضة في النموذج قبل حفظها، والمفتاح الجديد
 * إن كُتب وإلا المحفوظ. لا يُحفظ شيء ولا يُرسل محتوى، والطلب لا يُحسب على السقف (بضع وحدات).
 */
class TestAssistantConnection
{
    /**
     * @param  array<string, mixed>  $data
     * @return bool|null null إن نقصت الإعدادات
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): ?bool
    {
        if (! $actor->can('ai_assistant.manage')) {
            throw new AuthorizationException;
        }

        $validated = SaveAssistantSettings::validate($data);
        $saved = AssistantSettings::query()->orderBy('id')->first();
        $settings = new AssistantSettings(array_intersect_key($validated, array_flip(['provider', 'model', 'base_url'])));
        $typed = filled($validated['api_key'] ?? null);

        SaveAssistantSettings::guardKeyDestination($settings, $saved, $typed);
        $settings->api_key = $typed ? $validated['api_key'] : $saved?->apiKey();

        if (! $settings->hasUsableProvider()) {
            return null;
        }

        try {
            AssistantProviders::make($settings)->answer('Reply with the single word OK.', 'OK?');
        } catch (AssistantProviderException $exception) {
            Log::warning('AI assistant connection test failed: '.$exception->getMessage());

            return false;
        }

        return true;
    }
}
