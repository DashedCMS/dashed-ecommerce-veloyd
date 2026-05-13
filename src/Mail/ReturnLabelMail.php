<?php

namespace Dashed\DashedEcommerceVeloyd\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Verstuurt een retourlabel naar de klant met de PDF als bijlage.
 * Onderwerp en inhoud worden geconfigureerd via Customsetting keys
 * veloyd_return_label_email_subject en veloyd_return_label_email_content.
 * Variabelen in de tekst gebruiken de :variable: syntax.
 */
class ReturnLabelMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public Order $order;
    public string $labelPdfPath;
    public ?string $personalNote;

    public function __construct(Order $order, string $labelPdfPath, ?string $personalNote = null)
    {
        $this->order = $order;
        $this->labelPdfPath = $labelPdfPath;
        $this->personalNote = $personalNote;
    }

    public function build(): static
    {
        $siteId = $this->order->site_id ?: Sites::getActive();
        $siteName = (string) (Customsetting::get('site_name', $siteId) ?: config('app.name', 'Site'));
        $siteUrl = (string) (Customsetting::get('site_url', $siteId) ?: config('app.url', ''));
        $fromEmail = Customsetting::get('site_from_email', $siteId);

        $subjectTemplate = (string) (Customsetting::get(
            'veloyd_return_label_email_subject',
            $siteId,
            'Je retourlabel voor bestelling :orderId:',
            $this->order->locale
        ));

        $contentTemplate = (string) (Customsetting::get(
            'veloyd_return_label_email_content',
            $siteId,
            "<p>Beste :customerFirstName:,</p><p>Hierbij ontvang je het retourlabel voor je bestelling :orderId:. Print het label en plak het op het pakket.</p>",
            $this->order->locale
        ));

        $variables = [
            ':orderId:' => (string) $this->order->invoice_id,
            ':customerFirstName:' => (string) $this->order->first_name,
            ':customerLastName:' => (string) $this->order->last_name,
            ':firstName:' => (string) $this->order->first_name,
            ':lastName:' => (string) $this->order->last_name,
            ':siteName:' => $siteName,
            ':siteUrl:' => $siteUrl,
        ];

        $subject = strtr($subjectTemplate, $variables);
        if (trim($subject) === '') {
            $subject = 'Je retourlabel voor bestelling ' . $this->order->invoice_id;
        }

        $content = strtr($contentTemplate, $variables);

        $renderedBlocks = [];

        $renderedBlocks[] = view('dashed-core::emails.blocks.heading', [
            'text' => 'Je retourlabel',
            'level' => 'h1',
        ])->render();

        $renderedBlocks[] = view('dashed-core::emails.blocks.text', [
            'body' => $content,
        ])->render();

        if (! empty($this->personalNote)) {
            $renderedBlocks[] = view('dashed-core::emails.blocks.divider')->render();
            $renderedBlocks[] = view('dashed-core::emails.blocks.text', [
                'body' => '<p><strong>Persoonlijke notitie:</strong></p><p>' . nl2br(e($this->personalNote)) . '</p>',
            ])->render();
        }

        $primaryColor = Customsetting::get('mail_primary_color', $siteId) ?: '#A0131C';
        $textColor = Customsetting::get('mail_text_color', $siteId, '#ffffff');
        $backgroundColor = Customsetting::get('mail_background_color', $siteId, '#f3f4f6');
        $footerText = Customsetting::get('mail_footer_text', $siteId);

        $showLogo = (bool) Customsetting::get('mail_show_logo', $siteId, 1);
        $showSiteName = (bool) Customsetting::get('mail_show_site_name', $siteId, 1);
        $siteLogo = null;
        if ($showLogo && function_exists('mediaHelper')) {
            $logoId = Customsetting::get('mail_logo', $siteId) ?: Customsetting::get('site_logo', $siteId);
            if ($logoId) {
                $media = mediaHelper()->getSingleMedia($logoId);
                $siteLogo = $media->url ?? null;
            }
        }

        $mail = $this
            ->subject($subject)
            ->view('dashed-core::emails.layout')
            ->with([
                'blocks' => $renderedBlocks,
                'siteName' => $siteName,
                'siteLogo' => $siteLogo,
                'siteUrl' => $siteUrl,
                'showSiteName' => $showSiteName,
                'primaryColor' => $primaryColor,
                'textColor' => $textColor,
                'backgroundColor' => $backgroundColor,
                'footerText' => $footerText,
            ]);

        if ($fromEmail) {
            $mail->from($fromEmail, $siteName);
        }

        $mail->attach(\Illuminate\Support\Facades\Storage::disk('public')->path($this->labelPdfPath), [
            'as' => 'retourlabel-' . $this->order->invoice_id . '.pdf',
            'mime' => 'application/pdf',
        ]);

        return $mail;
    }
}
