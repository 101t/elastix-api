<?php

declare(strict_types=1);

namespace PbxApi\Contracts;

/**
 * Common interface that every PBX driver must implement.
 *
 * Each driver wraps a specific telephony back-end (Asterisk, Issabel,
 * FreePBX …) and exposes a uniform set of operations so that the API
 * layer can remain driver-agnostic.
 */
interface PbxDriverInterface
{
    // ----------------------------------------------------------------
    // Peer / endpoint discovery
    // ----------------------------------------------------------------

    /**
     * Return all registered SIP/PJSIP peers (extensions + trunks).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSipPeers(): array;

    /**
     * Return extension profile data (credentials, codec settings, etc.).
     *
     * @return array<string, mixed>
     */
    public function getSipExtensions(): array;

    // ----------------------------------------------------------------
    // Active call monitoring
    // ----------------------------------------------------------------

    /**
     * Return all currently active channels as structured data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveCalls(): array;

    /**
     * Return status information for a specific channel.
     *
     * @param string|null $channel  AMI channel identifier; null = all channels
     * @return string Raw AMI response
     */
    public function getChannelStatus(?string $channel = null): string;

    /**
     * Return all currently parked calls.
     *
     * @return string Raw AMI response
     */
    public function getParkedCalls(): string;

    // ----------------------------------------------------------------
    // System information
    // ----------------------------------------------------------------

    /**
     * Return CPU / memory / uptime metrics.
     *
     * @return array{datetime: string, users: string, usage: string}
     */
    public function getSystemResources(): array;

    /**
     * Return disk-usage metrics for common PBX directories.
     *
     * @return array<string, mixed>
     */
    public function getDiskUsage(): array;

    // ----------------------------------------------------------------
    // CDR (Call Detail Records)
    // ----------------------------------------------------------------

    /**
     * Query the CDR database with optional filters.
     *
     * @param array{
     *   start_date?: string,
     *   end_date?: string,
     *   field_name?: string,
     *   field_pattern?: string,
     *   status?: string,
     *   limit?: int,
     *   custom?: string
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function getCdr(array $filters = []): array;

    // ----------------------------------------------------------------
    // Extension CRUD
    // ----------------------------------------------------------------

    /**
     * Provision a new SIP/PJSIP extension.
     *
     * @param array<string, mixed> $data Extension parameters
     * @return bool
     */
    public function addExtension(array $data): bool;

    /**
     * Update an existing extension.
     *
     * @param array<string, mixed> $data Extension parameters (must include account)
     * @return bool
     */
    public function updateExtension(array $data): bool;

    /**
     * Delete an extension by account number.
     *
     * @param string $account
     * @return bool
     */
    public function deleteExtension(string $account): bool;

    // ----------------------------------------------------------------
    // Follow-Me CRUD
    // ----------------------------------------------------------------

    /**
     * Add a Follow-Me rule for an extension.
     *
     * @param array<string, mixed> $data
     * @return bool
     */
    public function addFollowMe(array $data): bool;

    /**
     * Update a Follow-Me rule.
     *
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateFollowMe(array $data): bool;

    /**
     * Delete the Follow-Me rule for an extension.
     *
     * @param string $grpnum Extension number
     * @return bool
     */
    public function deleteFollowMe(string $grpnum): bool;

    /**
     * Return the Follow-Me settings for a single extension.
     *
     * @param string $grpnum
     * @return array<int, array<string, mixed>>
     */
    public function getFollowMe(string $grpnum): array;

    /**
     * Return Follow-Me settings for all extensions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllFollowMe(): array;
}
