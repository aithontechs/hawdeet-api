<?php

namespace App\Services\Fpdi;

use setasign\Fpdi\Fpdi;

class TransparentFpdi extends Fpdi
{
    private array $gsList  = [];
    private int   $gsCount = 0;

    public function setAlpha(float $alpha): void
    {
        $alpha = round(max(0.0, min(1.0, $alpha)), 2);
        $key   = (string) $alpha;

        if (!isset($this->gsList[$key])) {
            $this->gsCount++;
            $this->gsList[$key] = [
                'name'  => 'TrAlpha' . $this->gsCount,
                'alpha' => $alpha,
                'objId' => null,
            ];
        }

        $this->_out('/' . $this->gsList[$key]['name'] . ' gs');
    }

    protected function _putresources(): void
    {
        foreach ($this->gsList as &$gs) {
            $this->_newobj();
            $gs['objId'] = $this->n;
            $this->_put('<< /Type /ExtGState');
            $this->_put('/ca ' . $gs['alpha']);
            $this->_put('/CA ' . $gs['alpha']);
            $this->_put('>>');
            $this->_put('endobj');
        }
        unset($gs);

        parent::_putresources();
    }

    protected function _putresourcedict(): void
    {
        parent::_putresourcedict();

        if (empty($this->gsList)) {
            return;
        }

        $this->_put('/ExtGState <<');
        foreach ($this->gsList as $gs) {
            $this->_put('/' . $gs['name'] . ' ' . $gs['objId'] . ' 0 R');
        }
        $this->_put('>>');
    }
}
