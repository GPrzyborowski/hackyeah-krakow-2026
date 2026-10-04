<?php

namespace App\Enums;

/**
 * Why a link to join a job-sharing pair cannot be used by the person who opened it.
 */
enum JoinLinkProblem: string
{
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Used = 'used';
    case NotCandidate = 'not_candidate';
    case OwnLink = 'own_link';
    case PairClosed = 'pair_closed';
    case PairFull = 'pair_full';
    case OfferClosed = 'offer_closed';
    case AlreadyPaired = 'already_paired';

    public function message(): string
    {
        return match ($this) {
            self::Invalid => 'Ten link do pary jest nieprawidłowy. Poproś koleżankę o nowy link.',
            self::Expired => 'Ten link wygasł. Poproś koleżankę o nowy link.',
            self::Used => 'Ktoś już dołączył do pary z tego linku. Poproś koleżankę o nowy link.',
            self::NotCandidate => 'Do pary mogą dołączyć tylko kandydatki. Wyloguj się i zaloguj na konto kandydatki.',
            self::OwnLink => 'To Twój link. Wyślij go koleżance, z którą chcesz aplikować w parze.',
            self::PairClosed => 'Ta para została rozwiązana albo jest już pełna, więc link nie działa.',
            self::PairFull => 'Do tej pary dołączyła już druga osoba.',
            self::OfferClosed => 'Ta oferta nie przyjmuje już zgłoszeń od par.',
            self::AlreadyPaired => 'Masz już parę w tej ofercie. Żeby dołączyć do innej, najpierw rozwiąż obecną w zakładce „Aplikuj w parze”.',
        };
    }
}
