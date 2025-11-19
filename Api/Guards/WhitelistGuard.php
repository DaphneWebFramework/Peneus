<?php declare(strict_types=1);
/**
 * WhitelistGuard.php
 *
 * (C) 2025 by Eylem Ugurel
 *
 * Licensed under a Creative Commons Attribution 4.0 International License.
 *
 * You should have received a copy of the license along with this work. If not,
 * see <http://creativecommons.org/licenses/by/4.0/>.
 */

namespace Peneus\Api\Guards;

use \Harmonia\Server;

/**
 * A guard that verifies whether the request originates from an allowed IP
 * address, and blocks all other requests.
 */
class WhitelistGuard implements IGuard
{
    private readonly array $whitelist;
    private readonly Server $server;

    /**
     * Constructs a new instance with a list of allowed IP addresses or CIDR
     * ranges.
     *
     * Only IPv4 addresses are supported.
     *
     * @param string ...$whitelist
     *   The IP addresses or CIDR ranges to allow. If no addresses are provided,
     *   no requests are allowed.
     */
    public function __construct(string ...$whitelist)
    {
        $this->whitelist = $whitelist;
        $this->server = Server::Instance();
    }

    /**
     * Verifies whether the request originates from an allowed IP address.
     *
     * @return bool
     *   Returns `true` if the request originates from an allowed address
     *   (either exact match or within a CIDR range), otherwise `false`.
     */
    public function Verify(): bool
    {
        if (empty($this->whitelist)) {
            return false;
        }
        $ip = $this->server->ClientAddress();
        foreach ($this->whitelist as $entry) {
            if ($ip === $entry) {
                return true;
            }
            if ($this->inCidr($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    protected function inCidr(string $ip, string $cidr): bool
    {
        $ip = \ip2long($ip);
        if ($ip === false) {
            return false;
        }
        if (\strpos($cidr, '/') === false) {
            return false;
        }
        [$subnet, $maskBits] = \explode('/', $cidr, 2);
        $subnet = \ip2long($subnet);
        if ($subnet === false) {
            return false;
        }
        if (!\ctype_digit($maskBits)) {
            return false;
        }
        $maskBits = (int)$maskBits;
        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }
        $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));
        return ($ip & $mask) === ($subnet & $mask);
    }
}
