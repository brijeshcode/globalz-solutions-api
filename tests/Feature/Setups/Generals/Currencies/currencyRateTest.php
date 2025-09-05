<?php

test('currency rate has no test ', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
