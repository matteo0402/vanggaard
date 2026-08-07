<?php

test('returns a successful health response', function () {
    $response = $this->get('/up');

    $response->assertOk();
});
