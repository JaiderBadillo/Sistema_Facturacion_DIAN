<?php
declare(strict_types=1);

// Contenedor de la factura al estilo AttachedDocument (UBL): lleva la factura XML original y la
// respuesta de validación. Es una simulación académica: estructura simplificada, sin firma digital
// y con un validador propio (Folio), no la DIAN. shortcut: sin XAdES; para producción usar un proveedor tecnológico.
final class FacturaContenedor
{
    private const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private function parte(DOMDocument $d, DOMElement $padre, string $nombre, string $nit, string $razon, string $correo = ''): void
    {
        $p = $padre->appendChild($d->createElementNS(self::CAC, "cac:$nombre"));
        $p->appendChild($d->createElementNS(self::CAC, 'cac:PartyIdentification'))->appendChild($d->createElementNS(self::CBC, 'cbc:ID', $nit));
        $p->appendChild($d->createElementNS(self::CAC, 'cac:PartyName'))->appendChild($d->createElementNS(self::CBC, 'cbc:Name', $razon));
        if ($correo !== '') {
            $p->appendChild($d->createElementNS(self::CAC, 'cac:Contact'))->appendChild($d->createElementNS(self::CBC, 'cbc:ElectronicMail', $correo));
        }
    }

    private function adjunto(DOMDocument $d, DOMElement $padre, string $contenido, string $descripcion): void
    {
        $ref = $padre->appendChild($d->createElementNS(self::CAC, 'cac:Attachment'))->appendChild($d->createElementNS(self::CAC, 'cac:ExternalReference'));
        $ref->appendChild($d->createElementNS(self::CBC, 'cbc:MimeCode', 'text/xml'));
        $ref->appendChild($d->createElementNS(self::CBC, 'cbc:EncodingCode', 'UTF-8'));
        $ref->appendChild($d->createElementNS(self::CBC, 'cbc:Description'))->appendChild($d->createCDATASection($contenido));
        $ref->setAttribute('descripcion', $descripcion);
    }

    /** Respuesta de validación simulada, equivalente a un ApplicationResponse. */
    private function respuestaValidacion(array $f): string
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $d->formatOutput = true;
        $r = $d->appendChild($d->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2', 'ApplicationResponse'));
        $r->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $r->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $r->appendChild($d->createElementNS(self::CBC, 'cbc:ID', $f['numero']));
        $r->appendChild($d->createElementNS(self::CBC, 'cbc:IssueDate', substr($f['fecha_registro'], 0, 10)));
        $r->appendChild($d->createElementNS(self::CBC, 'cbc:IssueTime', substr($f['fecha_registro'], 11)));
        $this->parte($d, $r, 'SenderParty', '000000000', 'Folio · validador simulado');
        $resp = $r->appendChild($d->createElementNS(self::CAC, 'cac:DocumentResponse'));
        $est = $resp->appendChild($d->createElementNS(self::CAC, 'cac:Response'));
        $est->appendChild($d->createElementNS(self::CBC, 'cbc:ResponseCode', 'VALIDADO'));
        $est->appendChild($d->createElementNS(self::CBC, 'cbc:Description', 'Documento validado por Folio (simulación académica, sin validez ante la DIAN)'));
        $ref = $resp->appendChild($d->createElementNS(self::CAC, 'cac:DocumentReference'));
        $ref->appendChild($d->createElementNS(self::CBC, 'cbc:ID', $f['numero']));
        $uuid = $ref->appendChild($d->createElementNS(self::CBC, 'cbc:UUID', $f['codigo_seguridad']));
        $uuid->setAttribute('schemeName', 'CUFE-SHA256');
        return $d->saveXML($d->documentElement);
    }

    public function generar(array $f, string $xmlOriginal, string $urlVerificacion): string
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $d->formatOutput = true;
        $raiz = $d->appendChild($d->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:AttachedDocument-2', 'AttachedDocument'));
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC);
        $raiz->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC);
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:UBLVersionID', 'UBL 2.1'));
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:ID', $f['numero']));
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:IssueDate', substr($f['fecha_registro'], 0, 10)));
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:IssueTime', substr($f['fecha_registro'], 11)));
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:DocumentType', 'Contenedor de Factura Electrónica (simulación)'));
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:ParentDocumentID', $f['numero']));
        $this->parte($d, $raiz, 'SenderParty', $f['emisor_nit'], $f['emisor_nombre']);
        $this->parte($d, $raiz, 'ReceiverParty', ($f['receptor_id'] ?? '') ?: 'N/D', $f['receptor'] ?: 'Sin especificar', $f['receptor_correo'] ?? '');
        $raiz->appendChild($d->createElementNS(self::CBC, 'cbc:Note', 'Verificación: ' . $urlVerificacion));

        // La factura va como texto (CDATA) sin su declaración XML, igual que en un contenedor real.
        $this->adjunto($d, $raiz, preg_replace('/^<\?xml[^>]*\?>\s*/', '', $xmlOriginal), 'Factura');
        $linea = $raiz->appendChild($d->createElementNS(self::CAC, 'cac:ParentDocumentLineReference'));
        $linea->appendChild($d->createElementNS(self::CBC, 'cbc:LineID', '1'));
        $docRef = $linea->appendChild($d->createElementNS(self::CAC, 'cac:DocumentReference'));
        $docRef->appendChild($d->createElementNS(self::CBC, 'cbc:ID', $f['numero']));
        $docRef->appendChild($d->createElementNS(self::CBC, 'cbc:UUID', $f['codigo_seguridad']))->setAttribute('schemeName', 'CUFE-SHA256');
        $docRef->appendChild($d->createElementNS(self::CBC, 'cbc:DocumentType', 'ApplicationResponse'));
        $this->adjunto($d, $docRef, $this->respuestaValidacion($f), 'Respuesta de validación');
        return $d->saveXML();
    }
}
