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
            self::Invalid => 'Ten link do pary jest nieprawidłowy. Poproś koleżankę o nowy.',
            self::Expired => 'Ten link do pary wygasł. Poproś koleżankę o nowy.',
            self::Used => 'Ktoś już dołączył do pary z tego linku.',
            self::NotCandidate => 'Do pary mogą dołączyć tylko kandydatki. Zaloguj się na konto kandydatki.',
            self::OwnLink => 'To Twój link. Wyślij go koleżance, z którą chcesz aplikować w parze.',
            self::PairClosed => 'Ta para została rozwiązana albo ma już komplet, więc link nie działa.',
            self::PairFull => 'Ta para ma już drugą osobę.',
            self::OfferClosed => 'Ta oferta nie przyjmuje już zgłoszeń par.',
            self::AlreadyPaired => 'Masz już parę do tej oferty. Żeby dołączyć do tej, najpierw rozwiąż tamtą.',
        };
    }
}
