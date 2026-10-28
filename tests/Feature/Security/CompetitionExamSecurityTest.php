<?php

use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| الاختبار الإلكتروني (الاختبار الأمني 0.14.0): قيمة الخيار في صفحة السؤال رمز معتم للمتقدم والسؤال، لا مفتاح الخيار
| (a وb...) الذي يكشف ترتيب إدخال المسؤول فيُسقط خلط الخيارات. والمفتاح نفسه، أو رمز متقدم آخر، يُرفض.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    Storage::disk('local')->put('competition-media/intro.mp4', mp4Contents(30));

    $this->competition = Competition::factory()->exam()->create(['slug' => 'exam', 'shuffle_options' => true]);
    $this->question = $this->competition->questions->values()->first();
    $this->actingAs(User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]))
        ->post('/competitions/exam/entry/start');
    $this->post('/competitions/exam/exam/register', ['full_name' => 'مشارك تجريبي', 'contact_email' => '', 'education_stage' => 'secondary', 'pledge' => '1']);
    $this->postJson('/competitions/exam/exam/intro/start')->assertOk();
    $this->travel(31)->seconds();
    $this->post('/competitions/exam/exam/intro/finish');
    $this->post('/competitions/exam/exam/instructions', ['accept' => '1']);
    $this->submission = CompetitionSubmission::query()->latest('id')->firstOrFail();
});

it('never shows an option key as the radio value on the question page', function () {
    $page = $this->get('/competitions/exam/exam/questions/1')->assertOk()->getContent();
    preg_match_all('/name="option" value="([^"]*)"/', $page, $values);
    $keys = $this->question->optionKeys();

    expect($values[1])->toHaveCount(count($keys))
        ->and(array_intersect($values[1], $keys))->toBe([])
        ->and($values[1])->toEqualCanonicalizing(array_map(fn (string $key) => $this->submission->optionToken($this->question->id, $key), $keys));
});

it('refuses the raw option key and the token of another applicant, and saves the key from its own token', function () {
    $other = CompetitionSubmission::factory()->for($this->competition)->create();

    $this->post('/competitions/exam/exam/answer', ['question' => $this->question->id, 'option' => 'a', 'go' => 'next'])->assertSessionHasErrors('option');
    $this->post('/competitions/exam/exam/answer', ['question' => $this->question->id, 'option' => $other->optionToken($this->question->id, 'a'), 'go' => 'next'])->assertSessionHasErrors('option');
    expect($this->submission->fresh()->answer($this->question))->toBeNull();

    $this->post('/competitions/exam/exam/answer', ['question' => $this->question->id, 'option' => $this->submission->optionToken($this->question->id, 'b'), 'go' => 'next'])->assertRedirect('/competitions/exam/exam/questions/2');
    expect($this->submission->fresh()->answer($this->question))->toBe('b');
});

it('keeps the chosen option checked by its token after moving back', function () {
    $token = $this->submission->optionToken($this->question->id, 'c');
    $this->post('/competitions/exam/exam/answer', ['question' => $this->question->id, 'option' => $token, 'go' => 'next']);

    expect($this->get('/competitions/exam/exam/questions/1')->getContent())->toMatch('/value="'.$token.'"[^>]*checked/');
});
