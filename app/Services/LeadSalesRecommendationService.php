<?php

namespace App\Services;

use App\Models\Lead;

class LeadSalesRecommendationService
{
    public function for(Lead $lead): array
    {
        $category = strtolower((string) $lead->category);
        $offers = match (true) {
            str_contains($category, 'dent'), str_contains($category, 'medical'), str_contains($category, 'health') => ['Appointment-focused website', 'Local SEO and Google profile optimization', 'Review and patient follow-up automation'],
            str_contains($category, 'real estate'), str_contains($category, 'property') => ['Lead-generation website or landing pages', 'Listing inquiry automation', 'CRM follow-up campaigns'],
            str_contains($category, 'restaurant'), str_contains($category, 'food') => ['Mobile-first website with online ordering', 'Local SEO and reputation management', 'Customer re-engagement campaigns'],
            str_contains($category, 'construction'), str_contains($category, 'contractor'), str_contains($category, 'home service') => ['Quote-request website', 'Local lead generation and SEO', 'Missed-call and estimate follow-up automation'],
            str_contains($category, 'retail'), str_contains($category, 'consumer') => ['E-commerce or conversion-focused website', 'Paid campaign landing pages', 'Email/SMS retention automation'],
            str_contains($category, 'software'), str_contains($category, 'technology') => ['Product website and conversion audit', 'B2B outbound lead generation', 'Demo-booking automation'],
            default => ['Website redesign and conversion optimization', 'SEO and lead generation', 'CRM outreach and follow-up automation'],
        };

        $signals = [];
        if (! $lead->website) $signals[] = 'No website is recorded: lead with a website build offer.';
        if (! $lead->email && ! $lead->corporate_email) $signals[] = 'No email is recorded: qualify the correct decision-maker and email first.';
        if ($lead->number_of_employees) $signals[] = "Company size: {$lead->number_of_employees} employees; tailor scope and pricing accordingly.";
        if ($lead->lead_score > 0) $signals[] = "CRM score is {$lead->lead_score}; prioritize discovery around the activity that created this score.";

        return [
            'headline' => $offers[0],
            'offers' => $offers,
            'signals' => $signals ?: ['Use a short discovery call to confirm their current acquisition channel, bottleneck, and budget.'],
            'opening' => 'Ask how they currently generate and follow up with new customer enquiries, then connect the biggest gap to one focused offer.',
        ];
    }
}
