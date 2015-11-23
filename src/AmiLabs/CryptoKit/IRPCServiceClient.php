<?php

namespace AmiLabs\CryptoKit;

/**
 * Interface for RPC Service client classes.
 */
interface IRPCServiceClient {
    /**
     * Executes RPC call.
     *
     * @param string $command           Command to execute
     * @param array $aParams            Parameters
     */
    public function exec($command, array $aParams);
}
