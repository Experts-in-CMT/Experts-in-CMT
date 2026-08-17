<?php
/**
 * Copyright (c) 2025-2026 Kenneth Raymond
 * All rights reserved.
 *
 * Part of the Experts in CMT platform.
 * Do not copy, modify, or redistribute without permission.
 */

/*
 * ------------------------------------------------------------
 * MU Plugin: EIC External Records Backfill
 * ------------------------------------------------------------
 * Writes external-record fields onto subtype records from the
 * signed cross-reference of EIC genes against five sources:
 *
 *   - GeneReviews       -> genereviews_url (gene chapter)
 *   - ClinGen validity  -> clingen_classification, clingen_disease,
 *                          clingen_mondo, clingen_validity_url
 *                          (Charcot-Marie-Tooth Disease GCEP only)
 *   - PanelApp 846      -> panelapp_rating, panelapp_votes, panelapp_url
 *   - ClinGen dosage    -> clingen_hi, clingen_ts, clingen_dosage_url
 *                          (stored only where evidence is available:
 *                          HI/TS score 1, 2, or 3)
 *   - Orphanet          -> orphanet_url (gene page)
 *
 * Matches on gene_symbol. Non-destructive and re-runnable: writes a
 * value only when the source has one and it differs from what is
 * stored; never clears a field. Refresh the embedded map from the
 * dataset downloads and re-run when the sources update.
 *
 * Location: wp-content/mu-plugins/eic-external-records-backfill.php
 */

if (!defined("ABSPATH")) {
    exit();
}

if (!is_admin()) {
    return;
}

final class EIC_External_Records_Backfill
{
    const CAP = "manage_options";
    const NONCE = "eic_external_records_backfill";

    /** Positional dataset slot -> ACF field key + admin label. */
    private static function fields(): array
    {
        return [
            0 => ["field_genereviews_url", "GeneReviews"],
            1 => ["field_clingen_classification", "ClinGen class"],
            2 => ["field_clingen_disease", "ClinGen disease"],
            3 => ["field_clingen_mondo", "ClinGen MONDO"],
            4 => ["field_clingen_validity_url", "ClinGen URL"],
            5 => ["field_panelapp_rating", "PanelApp rating"],
            6 => ["field_panelapp_votes", "PanelApp votes"],
            7 => ["field_panelapp_url", "PanelApp URL"],
            8 => ["field_clingen_hi", "Dosage HI"],
            9 => ["field_clingen_ts", "Dosage TS"],
            10 => ["field_clingen_dosage_url", "Dosage URL"],
            11 => ["field_orphanet_url", "Orphanet URL"],
        ];
    }

    /**
     * gene_symbol (HGNC-current, uppercase) => positional row:
     * [ genereviews_url, clingen_classification, clingen_disease,
     *   clingen_mondo, clingen_validity_url, panelapp_rating,
     *   panelapp_votes, panelapp_url, clingen_hi, clingen_ts,
     *   clingen_dosage_url, orphanet_url ]
     */
    private static function data(): array
    {
        return [
            "AARS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease axonal type 2N", "MONDO:0013212", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_92de3832-c272-4993-8586-288c6331dec2-2024-03-14T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/AARS/", "", "", "", "https://www.orpha.net/en/disease/gene/AARS1"],
            "ABHD12" => ["", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ABHD12/", "", "", "", "https://www.orpha.net/en/disease/gene/ABHD12"],
            "ADCY6" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ADCY6/", "", "", "", "https://www.orpha.net/en/disease/gene/ADCY6"],
            "AIFM1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "", "", "", "", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/AIFM1/", "", "", "", "https://www.orpha.net/en/disease/gene/AIFM1"],
            "ARHGEF10" => ["", "Limited", "autosomal dominant slowed nerve conduction velocity", "MONDO:0011998", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_b23121f9-57e1-48ac-a2bc-d5a293829530-2026-08-08T160000.000Z", "Amber", "44;22;33", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ARHGEF10/", "", "", "", "https://www.orpha.net/en/disease/gene/ARHGEF10"],
            "ATL1" => ["https://www.ncbi.nlm.nih.gov/books/NBK45978/", "Definitive", "neuropathy, hereditary sensory, type 1D", "MONDO:0013381", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_0c0282d1-5ab4-4a86-b1f6-165a3dbd515a-2022-02-10T020857.711Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ATL1/", "", "", "", "https://www.orpha.net/en/disease/gene/ATL1"],
            "ATL3" => ["", "Moderate", "neuropathy, hereditary sensory, type 1F", "MONDO:0014286", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_132d0be7-2003-43ed-a934-1cf60cf45773-2026-07-29T160000.000Z", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ATL3/", "", "", "", "https://www.orpha.net/en/disease/gene/ATL3"],
            "ATP1A1" => ["", "Definitive", "Charcot-Marie-tooth disease, axonal, type 2DD", "MONDO:0054833", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_65182954-6603-4880-85fa-352700bd784b-2026-07-29T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ATP1A1/", "", "", "", "https://www.orpha.net/en/disease/gene/ATP1A1"],
            "ATP7A" => ["https://www.ncbi.nlm.nih.gov/books/NBK1413/", "Moderate", "X-linked distal spinal muscular atrophy type 3", "MONDO:0010338", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_ec7c42f3-3cad-4020-aa75-cf103c5381ba-2026-07-29T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ATP7A/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=ATP7A", "https://www.orpha.net/en/disease/gene/ATP7A"],
            "BAG3" => ["https://www.ncbi.nlm.nih.gov/books/NBK1309/", "", "", "", "", "Green", "50;33;17", "https://panelapp.genomicsengland.co.uk/panels/846/gene/BAG3/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=BAG3", "https://www.orpha.net/en/disease/gene/BAG3"],
            "BICD2" => ["", "", "", "", "", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/BICD2/", "", "", "", "https://www.orpha.net/en/disease/gene/BICD2"],
            "BSCL2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1307/", "Definitive", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_02472719-c26d-40ff-8ad4-897ef7a6800a-2022-09-23T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/BSCL2/", "", "", "", "https://www.orpha.net/en/disease/gene/BSCL2"],
            "C19ORF12" => ["https://www.ncbi.nlm.nih.gov/books/NBK185329/", "", "", "", "", "Red", "0;100;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/C19ORF12/", "", "", "", "https://www.orpha.net/en/disease/gene/C19ORF12"],
            "CADM3" => ["", "Moderate", "Charcot-Marie-Tooth disease, axonal, type 2FF", "MONDO:0030433", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_bbe432b5-d085-4f99-91f9-a92f250dbf1b-2026-03-31T160000.000Z", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CADM3/", "", "", "", ""],
            "CCT5" => ["", "", "", "", "", "Red", "0;33;67", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CCT5/", "", "", "", "https://www.orpha.net/en/disease/gene/CCT5"],
            "CHCHD10" => ["https://www.ncbi.nlm.nih.gov/books/NBK1450/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CHCHD10/", "", "", "", "https://www.orpha.net/en/disease/gene/CHCHD10"],
            "CLTCL1" => ["https://www.ncbi.nlm.nih.gov/books/NBK481553/", "", "", "", "", "Red", "0;25;75", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CLTCL1/", "", "", "", "https://www.orpha.net/en/disease/gene/CLTCL1"],
            "CNTNAP1" => ["", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CNTNAP1/", "", "", "", "https://www.orpha.net/en/disease/gene/CNTNAP1"],
            "COA3" => ["", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/COA3"],
            "COA7" => ["", "", "", "", "", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/COA7/", "", "", "", ""],
            "COQ7" => ["https://www.ncbi.nlm.nih.gov/books/NBK410087/", "Strong", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_ef344e96-e52f-4c03-90bd-d78de5613ec7-2026-03-31T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/COQ7/", "", "", "", "https://www.orpha.net/en/disease/gene/COQ7"],
            "COX6A1" => ["", "", "", "", "", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/COX6A1/", "", "", "", "https://www.orpha.net/en/disease/gene/COX6A1"],
            "CRYAB" => ["", "", "", "", "", "Red", "0;33;67", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CRYAB/", "", "", "", "https://www.orpha.net/en/disease/gene/CRYAB"],
            "CTDP1" => ["https://www.ncbi.nlm.nih.gov/books/NBK25565/", "", "", "", "", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/CTDP1/", "", "", "", "https://www.orpha.net/en/disease/gene/CTDP1"],
            "DARS2" => ["https://www.ncbi.nlm.nih.gov/books/NBK43417/", "", "", "", "", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DARS2/", "", "", "", "https://www.orpha.net/en/disease/gene/DARS2"],
            "DCAF8" => ["", "", "", "", "", "Red", "0;25;75", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DCAF8/", "", "", "", "https://www.orpha.net/en/disease/gene/DCAF8"],
            "DCTN1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1450/", "", "", "", "", "Green", "83;0;17", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DCTN1/", "", "", "", "https://www.orpha.net/en/disease/gene/DCTN1"],
            "DGAT2" => ["", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/DGAT2"],
            "DHTKD1" => ["", "", "", "", "", "Green", "20;30;50", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DHTKD1/", "", "", "", "https://www.orpha.net/en/disease/gene/DHTKD1"],
            "DHX9" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DHX9/", "", "", "", ""],
            "DNAJB2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "neuronopathy, distal hereditary motor, autosomal recessive 5", "MONDO:0013947", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_6d726e4f-6edb-4d04-b2ae-2cd05ecdd8db-2026-08-08T160000.000Z", "Green", "50;50;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DNAJB2/", "", "", "", "https://www.orpha.net/en/disease/gene/DNAJB2"],
            "DNM2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1509/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_7bc54e5d-eed5-4d40-9e0d-143bfeb88cac-2020-10-27T131827.010Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DNM2/", "", "", "", "https://www.orpha.net/en/disease/gene/DNM2"],
            "DNMT1" => ["https://www.ncbi.nlm.nih.gov/books/NBK84112/", "", "", "", "", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DNMT1/", "", "", "", "https://www.orpha.net/en/disease/gene/DNMT1"],
            "DRP2" => ["", "", "", "", "", "Green", "57;29;14", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DRP2/", "", "", "", ""],
            "DST" => ["https://www.ncbi.nlm.nih.gov/books/NBK1369/", "Definitive", "hereditary sensory and autonomic neuropathy type 6", "MONDO:0013839", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_d90c7de8-a72a-4c54-bab8-fe9d4aef0409-2021-12-14T170000.000Z", "Green", "60;0;40", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DST/", "", "", "", "https://www.orpha.net/en/disease/gene/DST"],
            "DYNC1H1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_c1e12eb9-aea0-4597-8893-48df533f6ad9-2026-08-08T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/DYNC1H1/", "", "", "", "https://www.orpha.net/en/disease/gene/DYNC1H1"],
            "EGR2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_a6e83e27-0c9a-4475-92f5-745303931858-2021-11-17T033516.468Z", "Green", "88;12;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/EGR2/", "", "", "", "https://www.orpha.net/en/disease/gene/EGR2"],
            "ELP1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1180/", "Moderate", "Riley-Day syndrome", "MONDO:0009131", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_1fb7bfd2-ec58-4bb7-90ee-24ccf24fe942-2025-05-29T160000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ELP1/", "", "", "", "https://www.orpha.net/en/disease/gene/ELP1"],
            "FBLN5" => ["https://www.ncbi.nlm.nih.gov/books/NBK5201/", "Moderate", "demyelinating hereditary motor and sensory neuropathy", "MONDO:0018776", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_a863341a-9dcf-4a4f-a82d-7edb5e63fe92-2026-08-08T160000.000Z", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FBLN5/", "", "", "", "https://www.orpha.net/en/disease/gene/FBLN5"],
            "FBXO38" => ["", "Moderate", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_350c1f45-5afd-4f36-8973-94acaf7bb961-2026-08-08T160000.000Z", "Amber", "40;60;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FBXO38/", "", "", "", "https://www.orpha.net/en/disease/gene/FBXO38"],
            "FGD4" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_e0aae8cd-9d57-4d8c-83ae-ed968cf186bc-2020-04-14T131822.010Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FGD4/", "", "", "", "https://www.orpha.net/en/disease/gene/FGD4"],
            "FIG4" => ["https://www.ncbi.nlm.nih.gov/books/NBK1450/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_cbc44812-5fe4-4e11-ac89-58e4175d4463-2025-01-08T170000.000Z", "Green", "88;12;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FIG4/", "", "", "", "https://www.orpha.net/en/disease/gene/FIG4"],
            "FLVCR1" => ["", "", "", "", "", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FLVCR1/", "", "", "", "https://www.orpha.net/en/disease/gene/FLVCR1"],
            "FXN" => ["https://www.ncbi.nlm.nih.gov/books/NBK1281/", "", "", "", "", "Green", "71;14;14", "https://panelapp.genomicsengland.co.uk/panels/846/gene/FXN/", "", "", "", "https://www.orpha.net/en/disease/gene/FXN"],
            "GAN" => ["https://www.ncbi.nlm.nih.gov/books/NBK1136/", "Definitive", "giant axonal neuropathy 1", "MONDO:0009749", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_b9f65afa-5cb1-457e-abff-ab110145a32d-2022-02-10T021239.651Z", "Green", "88;12;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GAN/", "", "", "", "https://www.orpha.net/en/disease/gene/GAN"],
            "GARS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1242/", "Definitive", "Charcot-Marie-Tooth disease type 2D", "MONDO:0011091", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_168242d1-ef2e-486a-a45a-ed96187bd4d2-2023-01-10T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GARS/", "", "", "", "https://www.orpha.net/en/disease/gene/GARS1"],
            "GBF1" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GBF1/", "", "", "", ""],
            "GDAP1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_c6d93637-02f5-4f5d-b7a9-17356f084149-2020-07-28T144211.873Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GDAP1/", "", "", "", "https://www.orpha.net/en/disease/gene/GDAP1"],
            "GJB1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1374/", "Definitive", "Charcot-Marie-Tooth disease X-linked dominant 1", "MONDO:0010549", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_fcacd858-2376-4cc0-983a-156145e36595-2020-01-14T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GJB1/", "", "", "", "https://www.orpha.net/en/disease/gene/GJB1"],
            "GLE1" => ["", "", "", "", "", "Red", "0;100;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GLE1/", "", "", "", "https://www.orpha.net/en/disease/gene/GLE1"],
            "GNB4" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Moderate", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_a8cecdd7-06d8-4697-8cd9-32900c366371-2026-07-29T160000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/GNB4/", "", "", "", "https://www.orpha.net/en/disease/gene/GNB4"],
            "HADHB" => ["https://www.ncbi.nlm.nih.gov/books/NBK583531/", "", "", "", "", "Green", "67;17;17", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HADHB/", "", "", "", "https://www.orpha.net/en/disease/gene/HADHB"],
            "HARS1" => ["", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HARS/", "", "", "", "https://www.orpha.net/en/disease/gene/HARS1"],
            "HINT1" => ["", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_170aae71-9b16-49b1-be29-44f98db2f72e-2021-03-22T201109.360Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HINT1/", "", "", "", "https://www.orpha.net/en/disease/gene/HINT1"],
            "HK1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1375/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HK1/", "", "", "", "https://www.orpha.net/en/disease/gene/HK1"],
            "HSPB1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease axonal type 2F", "MONDO:0011687", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_1bec365e-7832-41f1-89cc-48b8ab570c76-2022-08-03T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HSPB1/", "", "", "", "https://www.orpha.net/en/disease/gene/HSPB1"],
            "HSPB3" => ["", "", "", "", "", "Red", "17;17;67", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HSPB3/", "", "", "", "https://www.orpha.net/en/disease/gene/HSPB3"],
            "HSPB8" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "neuronopathy, distal hereditary motor, autosomal dominant", "MONDO:0015362", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_044a2465-8e0a-44ff-8d00-1ec3a76183c8-2021-03-22T201855.441Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/HSPB8/", "", "", "", "https://www.orpha.net/en/disease/gene/HSPB8"],
            "IGHMBP2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "hereditary peripheral neuropathy", "MONDO:0020127", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_2717b35a-87a4-4f24-a762-53388db1bcb5-2023-09-06T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/IGHMBP2/", "", "", "", "https://www.orpha.net/en/disease/gene/IGHMBP2"],
            "INF2" => ["", "Definitive", "Charcot-Marie-Tooth disease dominant intermediate E", "MONDO:0013758", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_5215f2cf-99c9-405a-b221-18f412abd1f0-2021-03-22T202012.410Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/INF2/", "", "", "", "https://www.orpha.net/en/disease/gene/INF2"],
            "ITPR3" => ["", "Definitive", "Charcot-Marie-Tooth disease, demyelinating, type 1J", "MONDO:0859311", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_52136e55-cdc4-4bc2-a265-cacda8b269f2-2026-03-31T160000.000Z", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/ITPR3/", "", "", "", "https://www.orpha.net/en/disease/gene/ITPR3"],
            "JAG1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1273/", "", "", "", "", "Amber", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/JAG1/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=JAG1", "https://www.orpha.net/en/disease/gene/JAG1"],
            "KARS1" => ["", "", "", "", "", "Red", "17;33;50", "https://panelapp.genomicsengland.co.uk/panels/846/gene/KARS/", "", "", "", "https://www.orpha.net/en/disease/gene/KARS1"],
            "KIF1A" => ["https://www.ncbi.nlm.nih.gov/books/NBK49247/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/KIF1A/", "", "", "", "https://www.orpha.net/en/disease/gene/KIF1A"],
            "KIF5A" => ["https://www.ncbi.nlm.nih.gov/books/NBK1509/", "Definitive", "inherited neurodegenerative disorder", "MONDO:0024237", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_aad7dc3e-b664-4ffb-af0f-4eba346b43e7-2023-07-12T160000.000Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/KIF5A/", "", "", "", "https://www.orpha.net/en/disease/gene/KIF5A"],
            "LITAF" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_e7106097-314c-487a-9770-b7dea3f25f37-2026-07-29T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/LITAF/", "", "", "", "https://www.orpha.net/en/disease/gene/LITAF"],
            "LMNA" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "", "", "", "", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/LMNA/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=LMNA", "https://www.orpha.net/en/disease/gene/LMNA"],
            "LRP12" => ["", "", "", "", "", "Amber", "67;0;33", "https://panelapp.genomicsengland.co.uk/panels/846/gene/LRP12/", "", "", "", "https://www.orpha.net/en/disease/gene/LRP12"],
            "LRSAM1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease axonal type 2P", "MONDO:0013753", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_82dac4c0-801f-4704-b59d-0a9441423d5a-2023-11-28T170000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/LRSAM1/", "", "", "", "https://www.orpha.net/en/disease/gene/LRSAM1"],
            "MARS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Limited", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_8f042c42-893b-4c98-a719-0f778a54be43-2026-08-08T160000.000Z", "Red", "0;40;60", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MARS/", "", "", "", "https://www.orpha.net/en/disease/gene/MARS1"],
            "MCM3AP" => ["", "Definitive", "peripheral neuropathy, autosomal recessive, with or without impaired intellectual development", "MONDO:0029131", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_24bcbb92-9401-4659-beea-022646ee8930-2023-05-05T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MCM3AP/", "", "", "", ""],
            "MFN2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1511/", "Definitive", "Charcot-Marie-Tooth disease type 2A2", "MONDO:0012231", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_a04350b3-5fe9-438a-9e90-db0055ed15e7-2026-08-08T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MFN2/", "", "", "", "https://www.orpha.net/en/disease/gene/MFN2"],
            "MME" => ["", "Definitive", "Charcot-Marie-Tooth disease type 2T", "MONDO:0044640", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_6d426d95-70b5-4537-809f-9f2520fc39e9-2026-08-08T160000.000Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MME/", "", "", "", "https://www.orpha.net/en/disease/gene/MME"],
            "MORC2" => ["", "Definitive", "Charcot-Marie-Tooth disease axonal type 2Z", "MONDO:0014736", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_e3d5168d-b00c-475c-a696-d740675fbc84-2023-05-05T160000.000Z", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MORC2/", "", "", "", "https://www.orpha.net/en/disease/gene/MORC2"],
            "MPV17" => ["https://www.ncbi.nlm.nih.gov/books/NBK92947/", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MPV17/", "", "", "", "https://www.orpha.net/en/disease/gene/MPV17"],
            "MPZ" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_11f638d2-d8d2-4fe4-ab79-acff5cf32a18-2020-08-25T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MPZ/", "2", "1", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=MPZ", "https://www.orpha.net/en/disease/gene/MPZ"],
            "MT-ATP6" => ["https://www.ncbi.nlm.nih.gov/books/NBK1174/", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MT-ATP6/", "", "", "", "https://www.orpha.net/en/disease/gene/MT-ATP6"],
            "MT-TV" => ["https://www.ncbi.nlm.nih.gov/books/NBK1173/", "", "", "", "", "Amber", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MT-TV/", "", "", "", "https://www.orpha.net/en/disease/gene/MT-TV"],
            "MTMR2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "demyelinating hereditary motor and sensory neuropathy", "MONDO:0018776", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_44a837e2-ae06-4e4d-b7cf-ae2a3009b47d-2020-02-11T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MTMR2/", "", "", "", "https://www.orpha.net/en/disease/gene/MTMR2"],
            "MTRFR" => ["https://www.ncbi.nlm.nih.gov/books/NBK320989/", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/MTRFR"],
            "MYH14" => ["https://www.ncbi.nlm.nih.gov/books/NBK1434/", "", "", "", "", "Green", "57;14;29", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MYH14/", "1", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=MYH14", "https://www.orpha.net/en/disease/gene/MYH14"],
            "MYO9B" => ["", "", "", "", "", "Red", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/MYO9B/", "", "", "", ""],
            "NAGLU" => ["https://www.ncbi.nlm.nih.gov/books/NBK546574/", "", "", "", "", "Red", "0;50;50", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NAGLU/", "", "", "", "https://www.orpha.net/en/disease/gene/NAGLU"],
            "NARS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK612410/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NARS/", "", "", "", "https://www.orpha.net/en/disease/gene/NARS1"],
            "NDRG1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "", "", "", "", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NDRG1/", "", "", "", "https://www.orpha.net/en/disease/gene/NDRG1"],
            "NDUFS6" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NDUFS6/", "", "", "", "https://www.orpha.net/en/disease/gene/NDUFS6"],
            "NEFH" => ["", "Definitive", "Charcot-Marie-Tooth disease axonal type 2CC", "MONDO:0014836", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_8e1390ea-fd6c-4fa4-9b43-0eccff9e2829-2020-10-05T161645.817Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NEFH/", "", "", "", "https://www.orpha.net/en/disease/gene/NEFH"],
            "NEFL" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease type 2", "MONDO:0018993", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_49d4cf82-b365-456e-9e35-2b28a66b71ec-2023-01-10T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NEFL/", "", "", "", "https://www.orpha.net/en/disease/gene/NEFL"],
            "NGF" => ["https://www.ncbi.nlm.nih.gov/books/NBK481553/", "Strong", "hereditary sensory and autonomic neuropathy", "MONDO:0015364", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_f0b3691d-a3d3-42f6-9703-18af08f40688-2023-05-05T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NGF/", "", "", "", "https://www.orpha.net/en/disease/gene/NGF"],
            "NOTCH2NLC" => ["", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/NOTCH2NLC"],
            "NTRK1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1769/", "Definitive", "hereditary sensory and autonomic neuropathy type 4", "MONDO:0009746", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_e716345f-08da-4a06-b3e1-d07974a4305d-2021-11-17T030955.951Z", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/NTRK1/", "", "", "", "https://www.orpha.net/en/disease/gene/NTRK1"],
            "PDK3" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease X-linked dominant 6", "MONDO:0010479", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_508a1db7-0cf1-4c28-9203-89a3f4d7438d-2024-03-14T160000.000Z", "Green", "50;38;12", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PDK3/", "", "", "", "https://www.orpha.net/en/disease/gene/PDK3"],
            "PDXK" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PDXK/", "", "", "", ""],
            "PHYH" => ["https://www.ncbi.nlm.nih.gov/books/NBK1353/", "", "", "", "", "Green", "71;14;14", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PHYH/", "", "", "", "https://www.orpha.net/en/disease/gene/PHYH"],
            "PIEZO2" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PIEZO2/", "", "", "", "https://www.orpha.net/en/disease/gene/PIEZO2"],
            "PIGG" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PIGG/", "", "", "", "https://www.orpha.net/en/disease/gene/PIGG"],
            "PLEKHG5" => ["", "Definitive", "neuromuscular disease", "MONDO:0019056", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_2f2f5a6d-3b4f-4f4c-b560-50944b4ab34f-2023-09-06T160000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PLEKHG5/", "", "", "", "https://www.orpha.net/en/disease/gene/PLEKHG5"],
            "PMP2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Moderate", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_aa4c5730-f807-45d7-afe7-573a3960916f-2025-05-29T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PMP2/", "", "", "", "https://www.orpha.net/en/disease/gene/PMP2"],
            "PMP22" => ["https://www.ncbi.nlm.nih.gov/books/NBK1392/", "Definitive", "Charcot-Marie-Tooth disease type 1A", "MONDO:0007309", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_169edbc0-b942-43f1-885d-8d47ccdb6d90-2022-10-10T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PMP22/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=PMP22", "https://www.orpha.net/en/disease/gene/PMP22"],
            "PNKP" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PNKP/", "", "", "", "https://www.orpha.net/en/disease/gene/PNKP"],
            "POLG" => ["https://www.ncbi.nlm.nih.gov/books/NBK26471/", "", "", "", "", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/POLG/", "", "", "", "https://www.orpha.net/en/disease/gene/POLG"],
            "POLR3B" => ["https://www.ncbi.nlm.nih.gov/books/NBK99167/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/POLR3B/", "", "", "", "https://www.orpha.net/en/disease/gene/POLR3B"],
            "PRDM12" => ["https://www.ncbi.nlm.nih.gov/books/NBK481553/", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PRDM12/", "", "", "", "https://www.orpha.net/en/disease/gene/PRDM12"],
            "PRNP" => ["https://www.ncbi.nlm.nih.gov/books/NBK1229/", "", "", "", "", "Green", "60;0;40", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PRNP/", "", "", "", "https://www.orpha.net/en/disease/gene/PRNP"],
            "PRPS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "", "", "", "", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PRPS1/", "", "", "", "https://www.orpha.net/en/disease/gene/PRPS1"],
            "PRX" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease type 4", "MONDO:0018995", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_471d2fcc-f9d4-49d7-92b9-d16f5f751d83-2023-01-10T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/PRX/", "", "", "", "https://www.orpha.net/en/disease/gene/PRX"],
            "PSAT1" => ["https://www.ncbi.nlm.nih.gov/books/NBK592681/", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/PSAT1"],
            "RAB7A" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease type 2", "MONDO:0018993", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_222dbfa6-db75-42a0-bab6-338b46a316c3-2022-02-10T021034.172Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/RAB7A/", "", "", "", "https://www.orpha.net/en/disease/gene/RAB7A"],
            "REEP1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1509/", "", "", "", "", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/REEP1/", "", "", "", "https://www.orpha.net/en/disease/gene/REEP1"],
            "RETREG1" => ["https://www.ncbi.nlm.nih.gov/books/NBK49247/", "Definitive", "hereditary sensory and autonomic neuropathy", "MONDO:0015364", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_e0b9041f-06f2-47c8-b070-5d4cfaaeb2db-2024-07-08T160000.000Z", "Green", "71;14;14", "https://panelapp.genomicsengland.co.uk/panels/846/gene/RETREG1/", "", "", "", "https://www.orpha.net/en/disease/gene/RETREG1"],
            "RFC1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1138/", "", "", "", "", "Red", "50;17;33", "https://panelapp.genomicsengland.co.uk/panels/846/gene/RFC1/", "", "", "", "https://www.orpha.net/en/disease/gene/RFC1"],
            "RNF170" => ["", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/RNF170"],
            "RTN2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1509/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/RTN2/", "", "", "", "https://www.orpha.net/en/disease/gene/RTN2"],
            "SACS" => ["https://www.ncbi.nlm.nih.gov/books/NBK1255/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SACS/", "", "", "", "https://www.orpha.net/en/disease/gene/SACS"],
            "SARS1" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SARS/", "", "", "", "https://www.orpha.net/en/disease/gene/SARS1"],
            "SBF1" => ["", "Moderate", "Charcot-Marie-Tooth disease type 4B3", "MONDO:0014117", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_c0f814d9-02db-4c0c-8e1c-20a7759d993f-2026-08-08T160000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SBF1/", "", "", "", "https://www.orpha.net/en/disease/gene/SBF1"],
            "SBF2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease type 4B2", "MONDO:0011475", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_01893bf4-e95d-4206-9d2e-e3822f778b7a-2021-03-22T201309.257Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SBF2/", "", "", "", "https://www.orpha.net/en/disease/gene/SBF2"],
            "SCN11A" => ["https://www.ncbi.nlm.nih.gov/books/NBK481553/", "Definitive", "hereditary sensory and autonomic neuropathy type 7", "MONDO:0014244", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_abe8c0db-503c-46fa-a2a6-e4d6071219d0-2026-08-08T160000.000Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SCN11A/", "", "", "", "https://www.orpha.net/en/disease/gene/SCN11A"],
            "SCN9A" => ["https://www.ncbi.nlm.nih.gov/books/NBK49247/", "", "", "", "", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SCN9A/", "", "", "", "https://www.orpha.net/en/disease/gene/SCN9A"],
            "SCO2" => ["https://www.ncbi.nlm.nih.gov/books/NBK320989/", "Moderate", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_8c1cc0ca-0471-496b-941f-4c7a0ae854ae-2026-04-10T160000.000Z", "Green", "67;33;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SCO2/", "", "", "", "https://www.orpha.net/en/disease/gene/SCO2"],
            "SCYL1" => ["", "", "", "", "", "Amber", "0;100;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SCYL1/", "", "", "", "https://www.orpha.net/en/disease/gene/SCYL1"],
            "SEPTIN9" => ["", "Moderate", "neuralgic amyotrophy", "MONDO:0017362", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_0d2bb3b1-6fd0-4ee9-8273-322c33597eff-2026-08-08T160000.000Z", "Green", "83;17;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SEPT9/", "", "", "", "https://www.orpha.net/en/disease/gene/SEPTIN9"],
            "SETX" => ["https://www.ncbi.nlm.nih.gov/books/NBK1450/", "Definitive", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_1f355b8f-1cd9-401c-8f34-b9b3e2fe939b-2022-08-03T160000.000Z", "Green", "80;0;20", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SETX/", "", "", "", "https://www.orpha.net/en/disease/gene/SETX"],
            "SGPL1" => ["https://www.ncbi.nlm.nih.gov/books/NBK562988/", "", "", "", "", "", "", "", "", "", "", "https://www.orpha.net/en/disease/gene/SGPL1"],
            "SH3TC2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1340/", "Definitive", "Charcot-Marie-Tooth disease type 4C", "MONDO:0011113", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_8d2b214f-3086-4012-bf4b-a6e651618f0b-2026-08-08T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SH3TC2/", "", "", "", "https://www.orpha.net/en/disease/gene/SH3TC2"],
            "SIGMAR1" => ["", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SIGMAR1/", "", "", "", "https://www.orpha.net/en/disease/gene/SIGMAR1"],
            "SLC12A6" => ["https://www.ncbi.nlm.nih.gov/books/NBK1372/", "", "", "", "", "Green", "90;10;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SLC12A6/", "", "", "", "https://www.orpha.net/en/disease/gene/SLC12A6"],
            "SLC25A46" => ["", "Definitive", "neuropathy, hereditary motor and sensory, type 6B", "MONDO:0014671", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_9852fb40-0df1-4668-ba57-38a4971cb244-2020-05-26T160000.000Z", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SLC25A46/", "", "", "", "https://www.orpha.net/en/disease/gene/SLC25A46"],
            "SLC5A7" => ["https://www.ncbi.nlm.nih.gov/books/NBK1168/", "Moderate", "neuronopathy, distal hereditary motor, type 7A", "MONDO:0008024", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_c6e46608-98f5-4e4d-9bcf-22e42997af6e-2026-08-08T160000.000Z", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SLC5A7/", "", "", "", "https://www.orpha.net/en/disease/gene/SLC5A7"],
            "SORD" => ["", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_aac3d58a-b916-4b71-819f-ba4ef1de18d2-2023-04-13T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SORD/", "", "", "", "https://www.orpha.net/en/disease/gene/SORD"],
            "SPAST" => ["https://www.ncbi.nlm.nih.gov/books/NBK1160/", "", "", "", "", "Green", "60;20;20", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SPAST/", "3", "", "https://dosage.clinicalgenome.org/clingen_gene.cgi?sym=SPAST", "https://www.orpha.net/en/disease/gene/SPAST"],
            "SPG11" => ["https://www.ncbi.nlm.nih.gov/books/NBK1210/", "", "", "", "", "Green", "75;25;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SPG11/", "", "", "", "https://www.orpha.net/en/disease/gene/SPG11"],
            "SPTBN4" => ["https://www.ncbi.nlm.nih.gov/books/NBK559435/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SPTBN4/", "", "", "", ""],
            "SPTLC1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1390/", "Definitive", "neuropathy, hereditary sensory and autonomic, type 1A", "MONDO:0008086", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_609b735b-4782-47dc-ac38-f689671ccdac-2026-03-31T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SPTLC1/", "", "", "", "https://www.orpha.net/en/disease/gene/SPTLC1"],
            "SPTLC2" => ["", "Definitive", "neuropathy, hereditary sensory and autonomic, type 1C", "MONDO:0013337", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_2294e1cf-3aad-471e-a360-9f6f8c3b4d8f-2023-01-10T170000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SPTLC2/", "", "", "", "https://www.orpha.net/en/disease/gene/SPTLC2"],
            "SURF1" => ["https://www.ncbi.nlm.nih.gov/books/NBK320989/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SURF1/", "", "", "", "https://www.orpha.net/en/disease/gene/SURF1"],
            "SYT2" => ["https://www.ncbi.nlm.nih.gov/books/NBK1168/", "Moderate", "congenital myasthenic syndrome 7", "MONDO:0014468", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_ff8871e1-5eed-4414-8137-432d57f328d1-2023-07-12T160000.000Z", "Green", "57;43;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/SYT2/", "", "", "", "https://www.orpha.net/en/disease/gene/SYT2"],
            "TECPR2" => ["https://www.ncbi.nlm.nih.gov/books/NBK584409/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/TECPR2/", "", "", "", "https://www.orpha.net/en/disease/gene/TECPR2"],
            "TFG" => ["", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/TFG/", "", "", "", "https://www.orpha.net/en/disease/gene/TFG"],
            "TRIM2" => ["", "", "", "", "", "Green", "60;40;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/TRIM2/", "", "", "", "https://www.orpha.net/en/disease/gene/TRIM2"],
            "TRPV4" => ["https://www.ncbi.nlm.nih.gov/books/NBK201366/", "Definitive", "neuromuscular disease", "MONDO:0019056", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_b89b03c3-24c7-4424-989c-abd65eb2b7ec-2021-12-14T145310.482Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/TRPV4/", "", "", "", "https://www.orpha.net/en/disease/gene/TRPV4"],
            "TUBB3" => ["https://www.ncbi.nlm.nih.gov/books/NBK1348/", "", "", "", "", "Green", "80;20;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/TUBB3/", "", "", "", "https://www.orpha.net/en/disease/gene/TUBB3"],
            "UBA1" => ["https://www.ncbi.nlm.nih.gov/books/NBK2594/", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/UBA1/", "", "", "", "https://www.orpha.net/en/disease/gene/UBA1"],
            "VCP" => ["https://www.ncbi.nlm.nih.gov/books/NBK1450/", "", "", "", "", "Green", "83;0;17", "https://panelapp.genomicsengland.co.uk/panels/846/gene/VCP/", "", "", "", "https://www.orpha.net/en/disease/gene/VCP"],
            "VRK1" => ["", "", "", "", "", "Green", "50;50;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/VRK1/", "", "", "", "https://www.orpha.net/en/disease/gene/VRK1"],
            "VWA1" => ["", "", "", "", "", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/VWA1/", "", "", "", "https://www.orpha.net/en/disease/gene/VWA1"],
            "WARS1" => ["", "Limited", "distal hereditary motor neuropathy", "MONDO:0018894", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_93643521-0988-4c4b-b458-988044cfad6f-2026-08-08T160000.000Z", "Green", "100;0;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/WARS/", "", "", "", "https://www.orpha.net/en/disease/gene/WARS1"],
            "WNK1" => ["https://www.ncbi.nlm.nih.gov/books/NBK49247/", "Definitive", "neuropathy, hereditary sensory and autonomic, type 2A", "MONDO:0024309", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_28b83a4d-4a0f-4617-bc11-1effb9efe719-2023-05-05T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/WNK1/", "", "", "", "https://www.orpha.net/en/disease/gene/WNK1"],
            "YARS1" => ["https://www.ncbi.nlm.nih.gov/books/NBK1358/", "Definitive", "Charcot-Marie-Tooth disease", "MONDO:0015626", "https://search.clinicalgenome.org/kb/gene-validity/CGGV:assertion_9c326f90-be08-4bb3-9593-b2df48f29ca1-2020-04-28T160000.000Z", "Green", "86;14;0", "https://panelapp.genomicsengland.co.uk/panels/846/gene/YARS/", "", "", "", "https://www.orpha.net/en/disease/gene/YARS1"],
        ];
    }

    public static function init(): void
    {
        add_action("admin_menu", [__CLASS__, "menu"]);
    }

    public static function menu(): void
    {
        add_management_page(
            "External Records Backfill",
            "External Records Backfill",
            self::CAP,
            "eic-external-records-backfill",
            [__CLASS__, "render"]
        );
    }

    public static function render(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die("Insufficient permissions.");
        }
        eic_admin_tool_open("External Records Backfill");
        echo "<p>Writes GeneReviews, ClinGen gene-disease validity (CMT GCEP), PanelApp 846, " .
            "ClinGen dosage, and Orphanet fields onto subtype records, matched on " .
            "<code>gene_symbol</code>. Writes a value only when the source has one and it differs " .
            "from what is stored; never clears a field. Re-runnable.</p>";

        $action = $_POST["eic_action"] ?? "";
        if ($action && check_admin_referer(self::NONCE)) {
            self::run($action === "commit");
        }

        echo '<hr><form method="post">';
        wp_nonce_field(self::NONCE);
        echo '<p><button class="button button-primary" name="eic_action" value="dryrun">Dry run (no writes)</button></p>';
        echo '<p><label><input type="checkbox" name="confirm" value="1"> I have reviewed the dry run and want to write.</label></p>';
        echo '<p><button class="button button-primary eic-danger" name="eic_action" value="commit">Commit</button></p>';
        echo "</form>";
        eic_admin_tool_close();
    }

    private static function run(bool $commit): void
    {
        if ($commit && empty($_POST["confirm"])) {
            echo '<div class="notice notice-error"><p>Confirmation not checked. Nothing written.</p></div>';
            return;
        }

        $data = self::data();
        $fields = self::fields();

        $q = new WP_Query([
            "post_type" => "subtype",
            "post_status" => "any",
            "posts_per_page" => -1,
            "no_found_rows" => true,
        ]);

        $records_changed = 0;
        $fields_written = 0;
        $records_current = 0;
        $records_matched = 0;
        $seen = [];
        $cand_seen = [];

        echo "<h2>" . ($commit ? "Commit" : "Dry run") .
            " — scanning " . count($q->posts) . " records against " .
            count($data) . " genes with external records</h2>";
        echo '<table class="widefat striped"><thead><tr>' .
            "<th>Gene</th><th>Record</th><th>Field</th><th>Old</th><th>New</th></tr></thead><tbody>";

        foreach ($q->posts as $post) {
            $id = (int) $post->ID;
            $sym = strtoupper(trim((string) get_field("gene_symbol", $id)));
            if ($sym === "" || !isset($data[$sym])) {
                continue;
            }
            $seen[$sym] = true;
            $records_matched++;
            if ((bool) get_field("candidate_gene", $id)) {
                $cand_seen[$sym] = true;
            }
            $row = $data[$sym];
            $label = trim((string) get_field("subtype", $id));
            $label = $label !== "" ? $label : $post->post_title;

            $changed_this = 0;
            foreach ($fields as $i => $meta) {
                $new = isset($row[$i]) ? (string) $row[$i] : "";
                if ($new === "") {
                    continue;
                }
                $key = $meta[0];
                $name = substr($key, 6);
                $old = (string) get_field($name, $id);
                if ($old === $new) {
                    continue;
                }
                if ($commit) {
                    update_field($key, $new, $id);
                }
                $fields_written++;
                $changed_this++;
                echo "<tr style=\"background:#fff3cd\"><td><strong>" . esc_html($sym) .
                    "</strong></td><td>" . esc_html($label) . "</td><td>" . esc_html($meta[1]) .
                    "</td><td style=\"font-size:11px;color:#666\">" . esc_html(self::trunc($old)) .
                    "</td><td style=\"font-size:11px\">" . esc_html(self::trunc($new)) . "</td></tr>";
            }
            if ($changed_this > 0) {
                $records_changed++;
            } else {
                $records_current++;
            }
        }
        echo "</tbody></table>";

        echo "<p><strong>" . count($seen) . "</strong> genes matched across " .
            (int) $records_matched . " records — " . (int) $fields_written .
            ($commit ? " fields written across " : " fields would change across ") .
            (int) $records_changed . " records; " . (int) $records_current .
            " records already current.</p>";

        // Coverage metrics — gene-level, from the dataset for matched genes.
        $m_gr = $m_cg = $m_pa = $m_dose = $m_orph = 0;
        $cg_dist = [];
        $pa_dist = ["Green" => 0, "Amber" => 0, "Red" => 0];
        foreach (array_keys($seen) as $sym) {
            $r = $data[$sym];
            if ($r[0] !== "") {
                $m_gr++;
            }
            if ($r[1] !== "") {
                $m_cg++;
                $cg_dist[$r[1]] = ($cg_dist[$r[1]] ?? 0) + 1;
            }
            if ($r[5] !== "" && isset($pa_dist[$r[5]])) {
                $m_pa++;
                $pa_dist[$r[5]]++;
            }
            if ($r[8] !== "" || $r[9] !== "") {
                $m_dose++;
            }
            if ($r[11] !== "") {
                $m_orph++;
            }
        }
        $cg_order = ["Definitive", "Strong", "Moderate", "Limited", "Disputed", "Refuted", "No Known Disease Relationship", "Animal Model Only"];
        $cg_bits = [];
        foreach ($cg_order as $c) {
            if (!empty($cg_dist[$c])) {
                $cg_bits[] = esc_html($c) . " " . (int) $cg_dist[$c];
            }
        }
        $cand_n = count($cand_seen);

        echo '<h3 style="margin-top:1.5em">Coverage metrics</h3>';
        echo '<table class="widefat striped" style="max-width:760px"><tbody>';
        echo "<tr><td><strong>Genes matched</strong></td><td>" . count($seen) .
            " of " . count($data) . " in dataset · <strong>" . (int) $cand_n .
            "</strong> candidate, <strong>" . (count($seen) - (int) $cand_n) .
            "</strong> confirmed</td></tr>";
        echo "<tr><td><strong>Records carrying external records</strong></td><td>" .
            (int) $records_matched . "</td></tr>";
        echo "<tr><td><strong>GeneReviews chapter</strong></td><td>" . (int) $m_gr . " genes</td></tr>";
        echo "<tr><td><strong>ClinGen validity (CMT&nbsp;GCEP)</strong></td><td>" . (int) $m_cg .
            " genes" . ($cg_bits ? " — " . implode(", ", $cg_bits) : "") . "</td></tr>";
        echo "<tr><td><strong>PanelApp 846 rating</strong></td><td>" . (int) $m_pa .
            " genes — Green " . (int) $pa_dist["Green"] . ", Amber " . (int) $pa_dist["Amber"] .
            ", Red " . (int) $pa_dist["Red"] . "</td></tr>";
        echo "<tr><td><strong>ClinGen dosage</strong></td><td>" . (int) $m_dose . " genes</td></tr>";
        echo "<tr><td><strong>Orphanet gene page</strong></td><td>" . (int) $m_orph . " genes</td></tr>";
        echo "</tbody></table>";
    }

    private static function trunc(string $s): string
    {
        $s = trim($s);
        return strlen($s) > 48 ? substr($s, 0, 45) . "..." : $s;
    }
}

EIC_External_Records_Backfill::init();
