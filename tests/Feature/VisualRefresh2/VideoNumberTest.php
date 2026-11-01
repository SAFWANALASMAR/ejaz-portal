<?php

use App\Models\LibraryItem;

/*
| المرئيات مرقّمة (01، 02…) في المكتبة والرئيسية كبطاقات الموقع السابق (تدقيق المشرف)، والأقسام الأخرى بلا أرقام.
*/

it('numbers the video cards in the videos section and leaves the books unnumbered', function () {
    LibraryItem::factory()->video()->count(2)->create();
    LibraryItem::factory()->create();

    $this->get('/library/videos')->assertOk()
        ->assertSeeInOrder(['data-video-number', '01', 'data-video-number', '02'], false);

    $this->get('/library/books')->assertOk()->assertDontSee('data-video-number', false);
});
