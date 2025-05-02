<?php declare(strict_types=1);
/**
 * IMailerImpl.php
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
 * Defines the contract for all internal mailer implementations.
 */
interface IMailerImpl
{
    /**
     * Sets the recipient email address.
     *
     * @param string $email
     *   The destination email address.
     * @return static
     *   The current instance.
     */
    public function SetAddress(string $email): static;

    /**
     * Sets the subject line of the email.
     *
     * @param string $subject
     *   The subject text of the message.
     * @return static
     *   The current instance.
     */
    public function SetSubject(string $subject): static;

    /**
     * Sets the body of the email.
     *
     * @param string $body
     *   The full HTML or plain text content of the email.
     * @return static
     *   The current instance.
     */
    public function SetBody(string $body): static;

    /**
     * Sends the email message.
     *
     * @return bool
     *   Returns `true` if the message was sent successfully, `false` otherwise.
     */
    public function Send(): bool;
}
