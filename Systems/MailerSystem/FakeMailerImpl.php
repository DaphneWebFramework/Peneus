<?php declare(strict_types=1);
/**
 * FakeMailerImpl.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Systems\MailerSystem;

/**
 * Simulates email sending without dispatching any real messages.
 *
 * Used during development or testing to mimic a successful email delivery
 * without connecting to an SMTP server or external mail service.
 */
class FakeMailerImpl implements IMailerImpl
{
    public function SetAddress(string $email): static
    {
        return $this;
    }

    public function SetSubject(string $subject): static
    {
        return $this;
    }

    public function SetBody(string $body): static
    {
        return $this;
    }

    public function Send(): bool
    {
        return true;
    }
}
