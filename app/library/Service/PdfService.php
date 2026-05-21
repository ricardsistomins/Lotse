<?php 

namespace app\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    /**
     * Render an HTML string to a PDF binary string
     * 
     * @param string $html
     * @return string
     */
    public function render(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Dejavu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
}
