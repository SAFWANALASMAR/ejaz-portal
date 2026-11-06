<?php

/*
| User::uniqueViolationField: الحقل باسم القيد الذي تعارض، وقيد غير البريد والجوال يُرمى كما هو فلا يُنسب خطأً إلى الجوال
| (المشرف على #193).
*/

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

function uniqueViolation(string $index): UniqueConstraintViolationException
{
    return new UniqueConstraintViolationException(
        'pgsql',
        'update "users" set "email" = ?, "phone" = ? where "id" = ?',
        [],
        new PDOException('SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "'.$index.'"'),
    );
}

it('names the field by the violated index', function (string $index, string $field) {
    expect(User::uniqueViolationField(uniqueViolation($index)))->toBe($field);
})->with([
    ['users_verified_email_unique', 'email'],
    ['users_phone_unique', 'phone'],
    ['users_verified_phone_unique', 'phone'],
]);

it('rethrows a violation of any other index', function () {
    $exception = uniqueViolation('users_username_unique');

    try {
        User::uniqueViolationField($exception);
        $this->fail('expected the violation to be rethrown');
    } catch (UniqueConstraintViolationException $thrown) {
        expect($thrown)->toBe($exception);
    }
});
