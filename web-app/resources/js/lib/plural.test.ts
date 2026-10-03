import { describe, expect, it } from 'vitest';
import { parentReviewCountLabel, pluralize, reviewCountLabel } from './plural';

describe('pluralize', () => {
    it.each([
        [0, 'opinii'],
        [1, 'opinia'],
        [2, 'opinie'],
        [4, 'opinie'],
        [5, 'opinii'],
        [12, 'opinii'],
        [14, 'opinii'],
        [22, 'opinie'],
        [25, 'opinii'],
        [112, 'opinii'],
        [124, 'opinie'],
    ])('%i -> %s', (count, expected) => {
        expect(pluralize(count, 'opinia', 'opinie', 'opinii')).toBe(expected);
    });
});

describe('review count labels', () => {
    it('uses the singular parent only for one review', () => {
        expect(parentReviewCountLabel(1)).toBe('1 opinia rodzica');
        expect(parentReviewCountLabel(3)).toBe('3 opinie rodziców');
        expect(parentReviewCountLabel(5)).toBe('5 opinii rodziców');
        expect(reviewCountLabel(22)).toBe('22 opinie');
    });
});
