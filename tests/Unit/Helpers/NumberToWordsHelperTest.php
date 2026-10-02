<?php

use App\Helpers\NumberToWordsHelper;

it('spells whole rupees in Indian numbering', function () {
    expect(NumberToWordsHelper::inr(169920))->toBe('One Lakh Sixty Nine Thousand Nine Hundred Twenty');
});

it('spells small amounts', function () {
    expect(NumberToWordsHelper::inr(100))->toBe('One Hundred');
});

it('spells amounts in the crore range', function () {
    expect(NumberToWordsHelper::inr(2500000))->toBe('Twenty Five Lakh');
});

it('returns Zero for zero', function () {
    expect(NumberToWordsHelper::inr(0))->toBe('Zero');
});

it('includes paise when there is a fractional part', function () {
    expect(NumberToWordsHelper::inr(169920.50))->toBe('One Lakh Sixty Nine Thousand Nine Hundred Twenty and Fifty Paise');
});
