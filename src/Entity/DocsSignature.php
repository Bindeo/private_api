<?php

namespace Api\Entity;

use Bindeo\DataModel\DocsSignatureAbstract;
use Bindeo\DataModel\UserInterface;

class DocsSignature extends DocsSignatureAbstract
{

    /**
     * Returns an array with the object attributes
     * @return array
     */
    public function toArray()
    {
        $props = parent::toArray();

        // Bulk transaction
        if ($this->bulk instanceof BulkTransaction) {
            $props['bulk'] = $this->bulk->toArray();
        }

        // Files
        if ($this->files and is_array($this->files)) {

            // Convert each file to array
            $props['files'] = [];

            foreach ($this->files as $file) {
                $props['files'][] = $file->toArray();
            }
        }

        // Owner
        if ($this->issuer instanceof UserInterface) {
            $props['issuer'] = $this->issuer->toArray();
        }

        // Signers
        if ($this->signers and is_array($this->signers)) {

            // Convert each signer to array
            $props['signers'] = [];

            foreach ($this->signers as $signer) {
                $props['signers'][] = $signer->toArray();
            }
        }

        return $props;
    }
}