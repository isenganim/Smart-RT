<?php

use App\Support\AnnouncementHtml;

it('keeps supported announcement formatting and safe links', function () {
    $html = app(AnnouncementHtml::class)->sanitize(
        '<div><strong>Penting</strong> dan <em>segera</em></div>'
        .'<ul><li>Kerja bakti</li></ul>'
        .'<ol><li>Datang tepat waktu</li></ol>'
        .'<div><a href="https://example.com">Detail</a></div>'
    );

    expect($html)
        ->toContain('<strong>Penting</strong>')
        ->toContain('<em>segera</em>')
        ->toContain('<ul><li>Kerja bakti</li></ul>')
        ->toContain('<ol><li>Datang tepat waktu</li></ol>')
        ->toContain('href="https://example.com"');
});

it('removes unsupported markup and unsafe announcement links', function () {
    $html = app(AnnouncementHtml::class)->sanitize(
        '<script>alert(1)</script>'
        .'<div onclick="alert(1)">Aman</div>'
        .'<a href="javascript:alert(1)">Tautan</a>'
        .'<img src="https://example.com/image.jpg">'
    );

    expect($html)
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('<img')
        ->toContain('Aman')
        ->toContain('Tautan');
});
