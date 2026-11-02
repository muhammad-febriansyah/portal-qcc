<?php

use App\Models\User;

it('keeps chapter ten scoring routes closed by default without deleting their routes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('admin.rubrics.index'))->assertNotFound();
    $this->actingAs($user)->get(route('admin.scoring.index'))->assertNotFound();
    $this->actingAs($user)->get(route('admin.scoring.export'))->assertNotFound();
});

it('keeps appearance and avatar endpoints closed while retaining account profile access', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('appearance.edit'))->assertNotFound();
    $this->actingAs($user)->post(route('profile.avatar.update'))->assertNotFound();
    $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertNotFound();
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});
