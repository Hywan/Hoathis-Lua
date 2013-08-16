<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2013, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoathis')

/**
 * \Hoathis\Lua\Exception\Model
 */
-> import('Lua.Exception.Model');

}

namespace Hoathis\Lua\Model {

/**
 * Class \Hoathis\Lua\Model\Variable.
 *
 * Variable.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Value {

    protected $_value      = null;
    protected $_referenceType = false;

    const SCALAR = 0;
    const REFERENCE = 1;


    public function __construct ( $value, $referenceType = self::SCALAR) {

        $this->_referenceType = $referenceType;
        if ($this->_referenceType === self::REFERENCE) {
            $this->_value       = new self($value);
        } else {
            $this->_value = $value;
        }

        return;
    }

    public function setValue ( $value ) {
        if ($this->_referenceType === self::REFERENCE) {
            $old          = $this->_value->getValue();
            $this->_value->setValue($value);
        } else {
            $old          = $this->_value;
            $this->_value = $value;
        }
        return $old;
    }

    public function getValue ( ) {
        if ($this->_referenceType === self::REFERENCE) {
            return $this->_value->getValue();
        } else {
            return $this->_value;
        }
    }

    public function getReference() {
        if ($this->_referenceType === self::REFERENCE) {
            return $this->_value;
        } else {
            return $this;
        }
    }

    public function setReference($newReference) {
        if ($this->_referenceType === self::REFERENCE) {
            $old = $this->_value->getValue();
        } else {
            $old = $this->_value;
        }
        $this->_value = $newReference;
        $this->_referenceType = self::REFERENCE;
        return $old;
    }

    public function isReference() {
        return $this->_referenceType === self::REFERENCE;
    }

    public function getPHPValue() {
        $value = $this->getValue();
        if (true === is_array($value)) {
            $result = array();
            foreach ($value as $key => $val) {
                $result[$key] = $val->getPHPValue();
            }
            return $result;
        } else {
            return $value;
        }
    }

//    public function copyAsReferenceTo($dest) {
//        if ($dest instanceof Value) {
//            $dest->setValue($this->_value);
//        } else {
//            $dest->setValue($this);
//        }
//    }
}

}
