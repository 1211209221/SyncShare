<?php
session_start();

if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_SESSION['token'];

/**
 * =====================================================
 * GEMINI API KEY
 * =====================================================
 */
$API_KEY = 'AIzaSyDLq9Ig9JU3l2CRUFv21AGl0F1Gi3FOdEM';

/**
 * =====================================================
 * SOCIALBU HELPER
 * =====================================================
 */
/**
 * =====================================================
 * DATE RANGE
 * =====================================================
 */
$range = 30; // default last 30 days

$startDate = '2000-01-01';
$endDate = date('Y-m-d');

/**
 * =====================================================
 * SOCIALBU HELPER
 * =====================================================
 */
function socialbu_get($endpoint, $queryParams = [])
{
    global $token;

    $url =
        'https://socialbu.com/api/v1/' .
        $endpoint;

    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'raw' => $response,
        'data' => json_decode($response, true)
    ];
}

/**
 * =====================================================
 * FETCH TOP POSTS
 * =====================================================
 */

$topPosts = socialbu_get(
    "insights/posts/top_posts",
    [
        'start' => $startDate,
        'end' => $endDate,
        'metrics' => 'likes'
    ]
);

/* limit to 5 posts */
if (!empty($topPosts['data']['data'])) {
    $topPosts['data']['data'] = array_slice(
        $topPosts['data']['data'],
        0,
        5
    );
}
function quickFetch($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Mozilla/5.0"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
/**
 * =====================================================
 * GEMINI HELPER
 * =====================================================
 */
function generate_ai_response($prompt)
{
    global $API_KEY;

    $payload = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => $prompt
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.9,
            "topP" => 1,
            "topK" => 40,
            "maxOutputTokens" => 512
        ]
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [

        CURLOPT_URL =>
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . $API_KEY,

        // CURLOPT_URL =>
        //     "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $API_KEY,

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),

        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    $httpCode =
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError =
        curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        return [
            "success" => false,
            "summary" => "cURL Error: " . $curlError
        ];
    }

    $data = json_decode($response, true);

    if (isset($data['error'])) {

        return [
            "success" => false,
            "summary" =>
                "Gemini Error: " .
                ($data['error']['message'] ?? 'Unknown API error')
        ];
    }

    $summary =
        $data['candidates'][0]['content']['parts'][0]['text']
        ?? null;

    if (!$summary) {

        return [
            "success" => false,
            "summary" => "Gemini returned an empty response.",
            "debug" => $data
        ];
    }

    return [
        "success" => true,
        "summary" => $summary
    ];
}

/**
 * =====================================================
 * PIXAZO API KEY
 * =====================================================
 */
$pixazoApiKey = "17c0f129d252488eb099ad0f16da85d0";

/**
 * =====================================================
 * AI IMAGE GENERATOR (PIXAZO)
 * =====================================================
 */
function generate_ai_image($prompt)
{
    global $pixazoApiKey;

    if (!$prompt) {

        return [
            "success" => false,
            "error" => "Prompt is empty"
        ];
    }

    $pixazoUrl =
        "https://gateway.pixazo.ai/getImage/v1/getSDXLImage";

    $payload = json_encode([

        "prompt" => $prompt,

        "negative_prompt" =>
            "low quality, blurry, distorted, ugly",

        "height" => 1024,
        "width" => 1024,

        "num_steps" => 20,

        "guidance_scale" => 5,

        "seed" => rand(1, 999999)
    ]);

    $ch = curl_init($pixazoUrl);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            "Content-Type: application/json",

            "Ocp-Apim-Subscription-Key: $pixazoApiKey"
        ],

        CURLOPT_POSTFIELDS => $payload
    ]);

    $response = curl_exec($ch);

    $httpCode =
        curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $curlError =
        curl_error($ch);

    curl_close($ch);

    if ($curlError) {

        return [

            "success" => false,

            "error" =>
                "cURL Error: " . $curlError
        ];
    }

    $data =
        json_decode($response, true);

    if (
        $httpCode !== 200 ||
        empty($data['imageUrl'])
    ) {

        return [

            "success" => false,

            "error" =>
                "Image generation failed",

            "raw" => $data
        ];
    }

    return [

        "success" => true,

        "image" =>
            $data['imageUrl']
    ];
}


function renderPostEmbed($url) {

    if (!$url) return "";

    // ===========================
    // 🟠 TWITTER / X (OEMBED FIX)
    // ===========================
    if (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {

        $api = "https://publish.twitter.com/oembed?url=" . urlencode($url);
        $json = quickFetch($api);

        if (!empty($json['html'])) {
            return $json['html'];
        }

        return "<a href='{$url}' target='_blank'>{$url}</a>";
    }

    // ===========================
    // 🟣 MASTODON (YOUR WORKING METHOD)
    // ===========================
    if (strpos($url, 'mastodon') !== false || strpos($url, 'social') !== false) {
        return "
            <iframe
                src='{$url}/embed'
                style='width:100%; min-height:420px; border:0; border-radius:10px;'
                loading='lazy'>
            </iframe>
        ";
    }

    // ===========================
    // 🎥 YOUTUBE
    // ===========================
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {

        $id = getYouTubeId($url);

        return "
            <iframe
                src='https://www.youtube.com/embed/{$id}'
                style='width:100%; aspect-ratio:16/9; border:0; border-radius:10px;'
                allowfullscreen>
            </iframe>
        ";
    }

    // ===========================
    // 🔵 DEFAULT
    // ===========================
    return "<a href='{$url}' target='_blank' style='word-break:break-all;'>{$url}</a>";
}

/**
 * =====================================================
 * AJAX
 * =====================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json');

    $rawInput =
        file_get_contents("php://input");

    $input =
        json_decode($rawInput, true);

    /**
     * =====================================================
     * FETCH ACCOUNTS
     * =====================================================
     */
    if (
        isset($input['action']) &&
        $input['action'] === 'fetch_accounts'
    ) {

        $ch = curl_init(
            'https://socialbu.com/api/v1/accounts'
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        echo $response;
        exit;
    }

    /**
     * =====================================================
     * CONTENT GENERATION
     * =====================================================
     */
    if (
        isset($input['type']) &&
        $input['type'] === 'content_generation'
    ) {

        $presetKey =
            $input["preset_key"] ?? null;

        $postText =
            $input["text"] ?? "";

        $platform =
            $input["platform"] ?? "Instagram";

        $tone =
            $input["tone"] ?? "Professional";

        $purpose =
            $input["purpose"] ?? "Engagement";

        $responseFormat =
            $input["response_format"]
            ?? "Full Post";

        $metrics =
            $input["metrics"] ?? [];

        $topPosts =
            $metrics["top_posts"] ?? [];

        /**
         * =====================================================
         * FORMAT METRICS
         * =====================================================
         */
        $metricsText = "";

        foreach ($metrics as $k => $v) {

            if (is_array($v)) {
                $v = json_encode($v);
            }

            $metricsText .=
                strtoupper($k) .
                ": " .
                $v .
                "\n";
        }

        /**
         * =====================================================
         * FORMAT TOP POSTS
         * =====================================================
         */
        $topPostsText = "";

        foreach ($topPosts as $p) {

            $content =
                trim($p["content"] ?? "");

            if ($content === '') {
                $content =
                    '[No content available]';
            }

            $url =
                $p["url"] ?? "";

            $topPostsText .= "
            POST:
            {$content}

            URL:
            {$url}

            ----------------------
            ";
        }
        
/**
 * =====================================================
 * PLATFORM AI RULES
 * =====================================================
 */
$platformRules = [

    "Twitter/X" => [
        "limit" => 280,
        "style" => "
- Short punchy sentences
- Curiosity-driven hooks
- Repost optimized
- Conversational tone
- Internet-native language
- Max 3 hashtags
- Strong first line
",
        "engagement" => "
- Encourage reposts
- Encourage replies
- Optional CTA
"
    ],

    "Instagram" => [
        "limit" => 2200,
        "style" => "
- Influencer-style formatting
- Emoji-friendly
- Strong hook
- Visual storytelling
- Encourage saves/shares
- Use spacing for readability
- Include relevant hashtags
",
        "engagement" => "
- Encourage comments
- Encourage saves
- CTA encouraged
"
    ],

    "LinkedIn" => [
        "limit" => 1200,
        "style" => "
- Thought leadership tone
- Professional storytelling
- Insight-driven
- Authority building
- Clean line breaks
- Minimal hashtags
",
        "engagement" => "
- End with discussion question
- Encourage professional conversation
"
    ],

    "Facebook" => [
        "limit" => 500,
        "style" => "
- Casual
- Emotional
- Community-friendly
- Easy to skim
- Relatable tone
",
        "engagement" => "
- Encourage discussion
- CTA preferred
"
    ],

    "TikTok" => [
        "limit" => 300,
        "style" => "
- Trendy
- Fast-paced
- Gen-Z tone
- High energy
- Hook immediately
",
        "engagement" => "
- Encourage interaction
- Short CTA
"
    ],

    "Threads" => [
        "limit" => 500,
        "style" => "
- Conversational
- Human sounding
- Casual storytelling
- Relatable tone
",
        "engagement" => "
- Encourage replies
- Keep discussion flowing
"
    ],

    "YouTube" => [
        "limit" => 1000,
        "style" => "
- SEO friendly
- Curiosity-driven
- Viewer retention focused
- Search optimized wording
",
        "engagement" => "
- Encourage subscribe/comment
"
    ],

    "Mastodon" => [
        "limit" => 500,
        "style" => "
- Authentic
- Community-oriented
- Non-corporate tone
- Minimal hashtags
- Avoid clickbait
",
        "engagement" => "
- Encourage genuine discussion
"
    ]
];

$currentPlatformRules =
    $platformRules[$platform]
    ?? null;

        /**
         * =====================================================
         * PROMPT ROUTER
         * =====================================================
         */
        $prompt = "";

        /**
         * =====================================================
         * TRENDING IDEAS
         * =====================================================
         */
        if ($presetKey === "trending_ideas") {

            $prompt = "
You are a viral social media trend strategist.

Analyze the account analytics and top performing posts.

Generate 4 trending content ideas.

IMPORTANT:
Return ONLY valid JSON.

FORMAT:

{
  \"ideas\": [
    {
      \"title\": \"Short catchy title\",
      \"description\": \"A concise 1–2 sentence idea summary, max 240 characters.\",
      \"tag\": \"Educational\",
      \"platform\": \"Instagram Reels\"
    }
  ]
}

RULES:
- No markdown
- No code blocks
- No HTML
- JSON only
- Highly viral
- Human sounding
- Focus on engagement
- Modern internet trends
- Each idea must include a content category tag
- Tags should be things like:
  Educational,
  Storytelling,
  Controversial,
  Motivational,
  Trendjack,
  Engagement Bait,
  Personal Brand,
  Behind The Scenes,
  Relatable,
  Authority Building,
  Conversion Focused,
  Meme Content,
  Community Growth

METRICS:
$metricsText

TOP POSTS:
$topPostsText
";

        /**
         * =====================================================
         * INSTAGRAM REELS
         * =====================================================
         */
        } 
        
        
        /**
             * =====================================================
             * TRENDING HASHTAGS
             * =====================================================
             */
            elseif ($presetKey === "trending_hashtags") {
                $topic =
                    trim($input['topic'] ?? '');

                $prompt = "
                TOPIC / NICHE:
                $topic
                - If TOPIC / NICHE is provided, make all hashtags highly relevant to it
            You are a viral social media hashtag strategist.

            Generate 10 highly trending and viral hashtags.

            IMPORTANT:
            Return ONLY valid JSON.

            FORMAT:

            {
            \"hashtags\": [
                {
                \"hashtag\": \"#YourHashtag\",
                \"category\": \"Growth\"
                }
            ]
            }

            RULES:
            - No markdown
            - No HTML
            - No code blocks
            - JSON only
            - Generate exactly 12 hashtags
            - Mix viral + niche hashtags
            - Focus on discoverability
            - Focus on engagement growth
            - Modern internet trends
            - Human-like hashtag selection
            - Avoid repetitive hashtags
            - Include categories like:
            Viral,
            Engagement,
            Business,
            Creator,
            Marketing,
            Reels,
            AI,
            Startup,
            Lifestyle,
            Motivation

            METRICS:
            $metricsText

            TOP POSTS:
            $topPostsText
            ";}
        
        elseif ($presetKey === "instagram_reels") {

            $prompt = "
ROLE:
You are an elite Instagram strategist.

OBJECTIVE:
Generate 5 viral Instagram Reel ideas.

ACCOUNT ANALYTICS:
$metricsText

TOP POSTS:
$topPostsText

FOR EACH IDEA INCLUDE:
- Hook
- Concept
- Caption
- Retention tactic

FORMAT:
Return beautiful semantic HTML only.

Use:
<div class='ai-card'>

DO NOT:
- return markdown
- return scripts
";

        /**
         * =====================================================
         * TWITTER/X
         * =====================================================
         */
        } elseif ($presetKey === "twitter_posts") {

            $prompt = "
You are a Twitter/X growth expert.

Generate 5 highly engaging tweet ideas.

METRICS:
$metricsText

TOP POSTS:
$topPostsText

RULES:
- Short
- Punchy
- Curiosity driven
- Repostable
- Max 240 characters

Return semantic HTML only.
";

        /**
         * =====================================================
         * LINKEDIN
         * =====================================================
         */
        } elseif ($presetKey === "linkedin_posts") {

            $prompt = "
You are a LinkedIn thought leadership strategist.

Generate 5 professional LinkedIn posts.

Focus on:
- authority
- storytelling
- business insights
- engagement

METRICS:
$metricsText

TOP POSTS:
$topPostsText

Return semantic HTML only.
";

        /**
         * =====================================================
         * VIRAL HOOKS
         * =====================================================
         */
        } elseif ($presetKey === "viral_hooks") {

            $prompt = "
Generate 20 viral hooks.

Focus on:
- controversy
- curiosity
- emotional triggers
- retention

METRICS:
$metricsText

TOP POSTS:
$topPostsText

Return semantic HTML only.
";

        /**
         * =====================================================
         * CONTENT CALENDAR
         * =====================================================
         */
        } elseif ($presetKey === "content_calendar") {

            $prompt = "
Generate a 7-day content calendar.

For each day include:
- Platform
- Topic
- Hook
- Goal

METRICS:
$metricsText

TOP POSTS:
$topPostsText

Return semantic HTML only.
";

        /**
         * =====================================================
         * CAPTIONS
         * =====================================================
         */
        } elseif ($presetKey === "caption_generator") {

            $prompt = "
Generate 10 engaging captions.

Focus on:
- emotional engagement
- relatability
- comments
- shares

TOP POSTS:
$topPostsText

Return semantic HTML only.
";

        /**
         * =====================================================
         * DEFAULT
         * =====================================================
         */
        } else {

$prompt = "
You are an elite AI social media strategist.

PLATFORM:
$platform

TONE:
$tone

PURPOSE:
$purpose

USER REQUEST:
$postText

METRICS:
$metricsText

TOP POSTS:
$topPostsText

RESPONSE FORMAT:
$responseFormat

Generate highly optimized platform-native content.

IMPORTANT RESPONSE RULES:

If RESPONSE FORMAT is:
- Full Post → generate complete publish-ready content
- Caption → generate short caption-focused content
- Outline → generate bullet structure and content flow
- Reply → reply to the question

Adapt structure accordingly.

Adapt writing style, pacing, hooks,
and formatting specifically for the platform.

Return beautiful semantic HTML only.
";
        }

        if ($responseFormat === "Outline") {
            
            $previousAI = $input["previous_ai"] ?? "";

            $prompt .= "

            PREVIOUS AI OUTPUT FOR CONTEXT:
            $previousAI

            FORMAT STYLE:
            - Provide long descriptive outline of the proposed topic
            ";
            }

            if ($responseFormat === "Caption") {
            
                $previousAI = $input["previous_ai"] ?? "";

                $prompt .= "

                PREVIOUS AI OUTPUT FOR CONTEXT:
                $previousAI

                FORMAT STYLE:
                - Short-form
                - Highly engaging
                - Emoji optimized
                - Platform-native
                ";
                }


            if ($responseFormat === "Full Post") {
            
                $previousAI = $input["previous_ai"] ?? "";

                $platformLimits = [
                    "Twitter/X" => "STRICT LIMIT: Maximum 280 characters total.",
                    "Threads" => "Recommended limit: under 500 characters.",
                    "Instagram" => "Recommended limit: under 2200 characters.",
                    "Facebook" => "Recommended limit: under 500 characters for best engagement.",
                    "LinkedIn" => "Recommended limit: under 1200 characters.",
                    "TikTok" => "Recommended limit: under 300 characters.",
                    "YouTube" => "Recommended limit: under 1000 characters.",
                    "Mastodon" => "STRICT LIMIT: Maximum 500 characters."
                ];

                $limitInstruction =
                    $platformLimits[$platform]
                    ?? "Keep concise and platform optimized.";

                $prompt .= "
                PREVIOUS AI OUTPUT FOR CONTEXT:
                $previousAI
                FORMAT STYLE:
                - Return semantic HTML only
                - Do NOT return markdown
                - Do Not include anything after post content such as dates or 'just now'
                - Do NOT return scripts
                - Do NOT include indentation or leading spaces inside HTML text nodes
                - All text inside elements must start immediately with content (no leading whitespace)
                - Do NOT use <p> tags
                - All content must be inside <div class='post-content'>
                - Generate a fully styled social media post card
                - Make it look like a real social post UI
                - Include:
                    - profile avatar placeholder as grey circle
                    - creator name placeholder 
                    - username placeholder
                    - formatted post body
                    - hashtags if relevant
                    - CTA if relevant

                IMPORTANT:
                - Respect this platform rule:
                $limitInstruction

                - The generated post text itself MUST stay within platform limits
                - Keep it highly engaging and platform-native
                - Use clean modern HTML structure
                - Match the visual writing style of the selected platform
                
                IMPORTANT HTML STRUCTURE RULE:

                Inside the generated post card,
                ALWAYS insert this exact placeholder:

                <div class='ai-image-placeholder'></div>

                Place it:
                - AFTER the post header
                - BEFORE the post content

                EXAMPLE STRUCTURE:

                <div class='generated-post'>
                    <div class='post-header'>
                        ...
                    </div>

                    <div class='ai-image-placeholder'></div>

                    <div class='post-content'>
                        ...
                    </div>
                </div>
                ";
            }

            if ($responseFormat === "Reply") {

                $previousAI = $input["previous_ai"] ?? "";

                $prompt .= "

                You are replying to a previously generated AI post.

                PREVIOUS AI OUTPUT:
                $previousAI

                USER REQUEST:
                $postText

                IMPORTANT:
                - Base your reply on the PREVIOUS AI OUTPUT
                - Do NOT ignore it
                - Stay consistent with tone and content
                - Return semantic HTML only
                ";
            }

        $ai =
            generate_ai_response($prompt);

        if (!$ai['success']) {
            echo json_encode($ai);
            exit;
        }

        $summary = html_entity_decode($ai['summary'], ENT_QUOTES | ENT_HTML5);

        // $summary =
        //     $ai['summary'];

            /**
             * =====================================================
             * AUTO GENERATE IMAGE
             * =====================================================
             */

            $imagePrompt = "

            Create a highly engaging social media visual for:

            Platform:
            $platform

            Tone:
            $tone

            Purpose:
            $purpose

            Content:
            $postText

            Style:
            Modern, professional, viral, clean composition,
            high engagement social media aesthetic,
            high quality lighting, cinematic, ultra detailed.

            ";

            /**
             * =====================================================
             * GENERATE IMAGE PROMPT
             * =====================================================
             */

            $imagePromptRequest = "
            Create a cinematic social media image prompt.

            PLATFORM:
            $platform

            POST CONTENT:
            $summary

            STYLE RULES:
            - highly detailed
            - modern
            - viral social media aesthetic
            - realistic lighting
            - visually engaging
            - platform native
            - no text overlay
            - no watermark
            - concise but descriptive

            Return ONLY the image prompt text.
            ";
            
            // $imagePromptAI =
            //     generate_ai_response($imagePromptRequest);


            $imagePromptAI = [
                "success" => true,
                "summary" => $platform . " social media cinematic image, " . substr($postText, 0, 120)
            ];
            $imageUrl = null;

            if ($imagePromptAI['success']) {

                $imageGeneration =
                    generate_ai_image(
                        $imagePromptAI['summary']
                    );

                if ($imageGeneration['success']) {
                    $imageUrl =
                        $imageGeneration['image'];
                }
            }

            /**
             * =====================================================
             * TRENDING HASHTAGS RESPONSE
             * =====================================================
             */
            if ($presetKey === "trending_hashtags") {

                $parsed = json_decode($summary, true);

                if (
                    !$parsed ||
                    !isset($parsed['hashtags'])
                ) {

                    echo json_encode([
                        "success" => false,
                        "summary" => "Invalid hashtag JSON response.",
                        "raw" => $summary
                    ]);

                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "hashtags" => $parsed['hashtags']
                ]);

                exit;
            }

        /**
         * =====================================================
         * TRENDING JSON RESPONSE
         * =====================================================
         */
        if ($presetKey === "trending_ideas") {

            $parsed =
                json_decode($summary, true);

            if (
                !$parsed ||
                !isset($parsed['ideas'])
            ) {

                echo json_encode([
                    "success" => false,
                    "summary" => "Invalid JSON response.",
                    "raw" => $summary
                ]);

                exit;
            }

            echo json_encode([
                "success" => true,
                "type" => "trending_ideas",
                "ideas" => $parsed['ideas']
            ]);

            exit;
        }

        echo json_encode([
            "success" => true,
            "summary" => $summary,
            "image" => $imageUrl
        ]);

        exit;
    }
}

/**
 * =====================================================
 * FETCH INSIGHTS
 * =====================================================
 */
$followers =
    socialbu_get(
        'insights/accounts/followers',
        [
            'start' => $startDate,
            'end' => $endDate
        ]
    );

$followersByAccount = [];

$followersData = $followers['data']['data']['followers_by_account'] ?? [];

foreach ($followersData as $acc) {
    $followersByAccount[$acc['account_id']] = $acc['followers'] ?? 0;
}

$allAccountIds = array_keys($followersByAccount);

$accountsQuery = [];

foreach ($allAccountIds as $id) {
    $accountsQuery[] = "accounts[]=" . urlencode($id);
}

$accountsQueryString = implode('&', $accountsQuery);

$topPosts =
    socialbu_get(
        'insights/posts/top_posts',
        [
            'metrics' => 'likes',
            'start' => $startDate,
            'end' => $endDate
        ]
    );

/**
 * =====================================================
 * CLEAN POSTS
 * =====================================================
 */
$topPostsClean = [];

foreach (
    ($topPosts['data']['data'] ?? [])
    as $post
) {

    $topPostsClean[] = [

        'content' =>
            trim($post['content'] ?? ''),

        'engagement' =>
            $post['engagement'] ?? 0,

        'url' =>
            $post['permalink']
            ?? ''
    ];
}

$followersClean =
    $followers['data']['data'] ?? [];

$aiPayload = [

    "followers" => $followersClean,

    "top_posts" => $topPostsClean
];

?>
<?php

$topPostsText = '';

if (
    $topPosts['http_code'] === 200 &&
    !empty($topPosts['data']['data'])
) {
    foreach ($topPosts['data']['data'] as $post) {

        $content = trim($post['content'] ?? '');

        if ($content === '') {
            continue;
        }

        $topPostsText .=
            "POST:\n" .
            $content .
            "\n\n-------------------\n\n";
    }
}

if (
    isset($input['action']) &&
    $input['action'] === 'content_roadmap'
) {

    $topPostsText =
        $input['top_posts'] ?? '';

    $prompt = "
You are a social media growth strategist.

Analyze the following top-performing posts.

Determine:
- The attractive points of the top posts
- Mention the examples
- What posting style performs best
- What tone performs best
- What content should be created next that aligns with those posts.

Return ONE short paragraph only.

RULES:
- Maximum 90 words
- No markdown
- No bullet points
- No headings
- Give practical recommendations

TOP POSTS:

$topPostsText
";

    $ai = generate_ai_response($prompt);

    echo json_encode([
        'success' => true,
        'overview' => $ai['summary'] ?? ''
    ]);

    exit;
}
?>
<script>

const TOP_POSTS_TEXT =
<?= json_encode($topPostsText) ?>;

</script>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title> AI Content Studio </title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link href="https://fonts.googleapis.com/css?family=Lato|Poppins&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>

<style>

body{
    margin:0;
    background:#f3f6fb;
    font-family:'Poppins',sans-serif;
}

.chat-box{
    display:flex;
    flex-direction:column;
    gap:14px;
    overflow-y:auto;
    height:650px;
    padding:24px;
    background:#f0f2f6;
}

.msg-row{
    display:flex;
}

.msg{
    max-width:78%;
    padding:16px;
    border-radius:12px;
    font-size:16px;
}

.msg.user{
    margin-left:auto;
    background: #7a60c3;
    color:white;
}

.msg.ai{
    background:white;
}

.quick-actions{
    width:300px;
    background:white;
    padding:20px;
    border-right:1px solid #e5e7eb;
}

.quick-actions button{
    width:100%;
    margin-bottom:10px;
    border-radius:12px;
}

.pretty-ai-response{
    line-height:1.7;
}

.ai-card{
    background:#fff;
    /* border:1px solid #e5e7eb;
    border-radius:14px;
    padding:18px;
    margin-bottom:16px; */
}

.idea-wrapper{
    display:flex;
    flex-direction:column;
    gap:16px;
}

.idea-card:hover{
    background: #e4e6eb;
    transform:translateY(-2px);
}

.idea-title{
    font-size: 17px;
    font-weight: 800;
    color: #1f2937;
    margin-bottom: 10px;

    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;

    overflow: hidden;
}

.idea-meta{
    display:flex;
    gap:10px;
    margin-top:10px;
    margin-bottom:10px;
}

.idea-badge{
    background:white;
    color:#7a60c3;
    padding:5px 10px;
    border-radius: 7px;
    font-size:12px;
    font-weight:600;
}

.typing-loader{
    display:flex;
    gap:6px;
}

.typing-loader span{
    width:8px;
    height:8px;
    border-radius:50%;
    background:#888;
    animation:bounce 1s infinite;
}

.typing-loader span:nth-child(2){
    animation-delay:0.2s;
}

.typing-loader span:nth-child(3){
    animation-delay:0.4s;
}

/* =====================================================
   PRIMARY BRAND COLOR
===================================================== */

:root{
    --brand:#7a60c3;
    --brand-hover:#0394bb;
}

/* primary buttons */
.btn-primary{
    background:var(--brand) !important;
    border-color:var(--brand) !important;
}

.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active{
    background:var(--brand-hover) !important;
    border-color:var(--brand-hover) !important;
}

/* outline buttons */
.btn-outline-primary{
    color:var(--brand) !important;
    border-color:var(--brand) !important;
    background:transparent !important;
}

.btn-outline-primary:hover,
.btn-outline-primary:focus,
.btn-outline-primary:active{
    background:var(--brand) !important;
    border-color:var(--brand) !important;
    color:white !important;
}

/* send button */
.fa-paper-plane{
    color:white;
}

/* badges */
.idea-badge{
    background:white;
    color:var(--brand);
}

/* user message bubble */
.msg.user{
    background:var(--brand);
}

/* textarea focus */
.form-control:focus{
    border-color:var(--brand) !important;
    box-shadow:
        0 0 0 0.2rem rgba(4,163,206,0.15) !important;
}

/* links */
a{
    color:var(--brand);
}

/* scrollbar (optional) */
.chat-box::-webkit-scrollbar-thumb{
    background:var(--brand);
    border-radius:999px;
}

@keyframes bounce{

    0%,80%,100%{
        transform:scale(0);
    }

    40%{
        transform:scale(1);
    }
}

textarea{
    resize:none;
    height:98px !important;
    min-height:98px !important;
    max-height:98px !important;

    overflow-y:hidden;
    white-space:nowrap;

    border-radius:8px !important;
    background:#f0f2f6 !important;
    border:1px solid #f0f2f6 !important;

    padding-top:14px !important;
    padding-bottom:14px !important;
}

.ai-controls select{
    background-color: white;
    border: 1px solid white;
    height: 39px;
    font-size:  15px;
    border-radius:6px !important;
}
.ui-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    color: #9a97a7;
}
.container {
    padding: 0px 1.5rem !important;
}
.idea-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:16px;
}

.idea-card{
    background:#f7f8fc;
    border:1px solid #edf0f7;
    border-radius:14px;
    padding:18px;
    position:relative;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:space-between;

    opacity:0;
    animation:fadeUp .85s ease forwards;

    transition:
        transform .2s ease,
        border-color .2s ease;
}

.idea-title{
    font-size:17px;
    font-weight:800;
    color:#1f2937;
    margin-bottom:10px;
}

.idea-description{
    color:#5b6475;
    line-height:1.6;
    font-size:14px;
    min-height:72px;
}

.idea-meta{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:14px;
    margin-bottom:14px;
}

.idea-badge{
    background:white;
    border:1px solid #e5e7eb;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    color:#5b5bd6;
}

.use-btn{
    width:100%;
    border:none;
    background-color: #7a60c3 !important;
    color:white;
    height:42px;
    border-radius:10px;
    font-weight:700;
}

.ui-container .use-btn:hover{
    transform:scale(1.035) !important;
}

.ui-container button{
    transition: 0.15s !important;
    background-color: #7a60c3 !important;
}

.ui-container button:hover{
    background-color: #6146aa !important;
}
@keyframes fadeUp{
    from{
        opacity:0;
        transform:translateY(14px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

.idea-card{
    animation:fadeUp .85s ease forwards;
    opacity:0;
    transition:.2s;
}

.idea-card:hover{
    border-color:#dfe6ff;
}

.btn-primary.create-post{
    background: white !important;
    color: #7a60c3;
    border: 0;
    transition: 0.2s;
}

.btn-primary.create-post:hover{
    background: #eeeeee !important;
    color: #7a60c3;
    transform: scale(1.05);
}
.horizontal-scroll {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 10px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
}
.post-card {
    flex: 0 0 350px;
    scroll-snap-align: start;
}
.horizontal-scroll .ui-container {
    border-radius: 12px;
    margin: 0px !important;
    height: 100%;
    display: flex !important;
    justify-content: space-between;
}
.twitter-tweet-rendered {
    margin: 0px !important;
}
.generated-post{
    background:white;
    border-radius:18px;
    padding:18px;
    max-width:700px;
    margin:auto;
}

.post-header{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:0px;
}

.avatar{
    width:48px;
    height:48px;
    border-radius:50%;
    background:linear-gradient(135deg,#7a60c3,#04a3ce);
    flex-shrink:0;
}

.user-meta{
    display:flex;
    flex-direction:column;
}

.user-meta .name{
    font-weight:700;
    color:#111827;
    font-size:15px;
}

.user-meta .username{
    font-size:13px;
    color:#6b7280;
}

.post-content{
    font-size:15px;
    line-height:1.8;
    color:#1f2937;
    white-space:pre-wrap;
    word-break:break-word;
}

.post-footer{
    margin-top:18px;
    display:flex;
    gap:18px;
    color:#6b7280;
    font-size:14px;
}

.post-pill{
    background:#f3f4f6;
    border-radius:999px;
    padding:6px 12px;
    font-size:12px;
    font-weight:600;
    display:inline-block;
    margin-top:12px;
}

.generated-image-wrapper{
    /* padding: 0px 15px; */
}

.generated-ai-image{
    width:100%;
    border-radius:16px;
    display:block;
    border:1px solid #e5e7eb;
}

.use-image-btn{
    margin-top:12px;
    width:100%;
    border-radius:12px;
    height:44px;
    font-weight:700;
}
.generated-post p{
    margin: -15px 0px !important;
    padding: 0 !important;
}

.generated-post .post-content{
    display: flex;
    flex-direction: column;
}
.post-content ul,
.post-content ol {
    margin: 6px 0 !important;
    padding-left: 18px;
}

.post-content li {
    margin: 2px 0 !important;
    line-height: 1.4;
}

.generated-post{
    width: 400px;
}

button.btn.btn-primary {
    color: #fff;
    background: linear-gradient(to right, #7a60c3 0%, #7a60c3 100%) !important;
    border-color: #7a60c3 !important;
}

button.btn.btn-primary:hover{
    background: linear-gradient(to right, #6146aa 0%, #6146aa 100%) !important;
    border-color: #6146aa !important;
}

a.btn.btn-primary {
    color: #fff;
    background: linear-gradient(to right, #7a60c3 0%, #7a60c3 100%) !important;
    border-color: #7a60c3 !important;
    transition: 0.15s;
}

a.btn.btn-primary:hover{
    background: linear-gradient(to right, #6146aa 0%, #6146aa 100%) !important;
    border-color: #6146aa !important;
    transform: scale(1.035);
}

.hero a.btn.btn-primary {
    color: #6146aa !important;
    background: #fff !important;
    border-color: #fff !important;
}

.hero a.btn.btn-primary:hover{
    background: #eeeeee !important;
    border-color: #eeeeee !important;
}
.hashtag-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
    gap:14px;
    margin-top:15px;
}

.hashtag-card{
    background: #f7f8fc;
    border: 1px solid #edf0f7;
    border-radius:14px;
    padding: 9px 14px;
    transition:0.25s ease;
    cursor:pointer;
    position:relative;
    overflow:hidden;
}

.hashtag-card:hover{
    transform:translateY(-3px);W
    border-color:rgba(255,255,255,0.2);
    background: #e4e6eb;
}

.hashtag-text{
    font-size:15px;
    font-weight:700;
    word-break:break-word;
    color: #1f2937;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hashtag-meta{
    font-size:12px;
    opacity:0.7;
    color: #9a97a7;
}

.hashtag-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.copy-icon{
    opacity:0;
    transform:translateX(6px);
    transition:all .2s ease;
    color:#9a97a7;
    cursor:pointer;
    width: 0px;
    right: 25px;
    position: absolute;
}

.hashtag-card:hover .copy-icon{
    opacity:1;
    transform:translateX(0);
}

.copy-tooltip{
    position:absolute;
    right:0;
    bottom:calc(100% + 8px);

    background:#1f1f1f;
    color:#fff;

    padding:6px 10px;
    border-radius:8px;

    font-size:12px;
    white-space:nowrap;

    opacity:0;
    pointer-events:none;

    transition:all .2s ease;
}

.copy-icon:hover .copy-tooltip{
    opacity:1;
}
</style>
</head>
<body>
    <div class="wrapper">
        <div class="d-flex">
            <?php include 'sidebar.php'; ?>
            <div style="width:100%;">
                <div class="py-3 px-3 d-flex justify-content-between align-items-center" style="background-color:white;margin-bottom: 20px;">
                    <h1 class="mb-0" style="font-size: 30px; font-weight: 800;">AI Content Studio</h1>
                    <div class="dropdown">
                        <button class="btn dropdown-toggle signout" type="button" data-bs-toggle="dropdown"
                            style="background:none;color:#312b2f !important;font-weight:bold;margin:0!important;">
                            <i class="fas fa-user" style="padding-right:6px;"></i>
                            <?= htmlspecialchars($_SESSION['user_email'] ?? 'Account') ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item text-danger signout_dropdown" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> <b>Logout</b>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex">
                    <div class="dashboard container container-fluid" id="dashboard">
                        <div>
                            <div style="width: 53%; margin: 0px 20px 15px 10px; color: #44424d;">
                                <a href="dashboard.php">Generate</a> > <a style="color: #7a60c3 !important; font-weight: bold;">AI Content Studio</a>
                            </div>
                            <div style="width: 35%; margin: 0px 10px;"></div>
                        </div>
                        <!-- HERO -->
                        <div class="ui-container hero" style="background:linear-gradient(135deg,#04a3ce 0%,#5b5bd6 55%,#7a60c3 100%);color:white!important;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3" style="width: 100%;">
                                <div class="d-flex justify-content-between flex-direction-row" style="width: 100%; align-items: center;">
                                    <div>
                                        <h3 style="font-weight:800;margin:0;">
                                            Welcome to AI Content Studio <i class="fas fa-magic" style="padding-left: 10px;"></i>
                                            <i class="fas fa-sparkles ms-2"></i>
                                        </h3>

                                        <div style="opacity:.92;font-size:14px;margin-top:6px;max-width:760px;">
                                            Generate high-performing social content for your accounts — Viral hooks, reels, captions, Twitter posts, LinkedIn content, and AI-powered growth ideas instantly using your analytics.
                                        </div>
                                    </div>
                                    <div>
                                        <a href="post-new.php" class="add-account btn btn-primary w-100 create-post">
                                            <i class="fas fa-plus"></i><b> Create New Post</b>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ui-container">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h5 style="margin: 0;">
                                    <i class="fas fa-star" style="font-size: 18px; padding-right: 10px;"></i>
                                    <strong>Top Content Drivers</strong>
                                </h5>
                            </div>
                            <hr>

                            <div id="aiOverviewCard" style=" margin-top:7px; border-radius: 12px; background:#f3f4f6; border:1px solid #f3f4f6; padding: 7px 15px; line-height:1.7; color:#374151; min-height:90px;">

                                <div id="aiOverviewLoading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    Generating content plan...
                                </div>

                                <div id="aiOverviewText" style="display: block;">
                                    <div id="overviewTyping" style="
                                        font-size:15px;
                                        color:#374151;
                                    ">No overview generated.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display:flex; justify-content: space-between;">
                            <div class="ui-container" style="align-items: center; width: 455px; min-height: 600px;">

                                <div class="d-flex justify-content-between">

                                    <h5>
                                        <i class="fas fa-hashtag"
                                        style="padding-right: 10px; font-size: 18px;">
                                        </i>

                                        <strong>Trending Hashtags</strong>
                                    </h5>

                                    <button class="btn btn-light" onclick="generateTrendingHashtags()"  style="border-radius:10px;font-weight:700;padding:5px 18px; color: white;"><i class="fas fa-redo-alt" style="padding-right: 7px;"> </i> Refresh </button>
                                </div>

                                <hr style="margin-top: 7px;">

                                <!-- TOPIC INPUT -->
                                <textarea id="hashtagTopic" class="form-control" placeholder="Enter a topic, niche, product, brand, or post idea..." style="     min-height: 55px !important; max-height: 55px !important; resize:none; border-radius:14px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); margin-bottom:18px; "
                                ></textarea>

                                <!-- HASHTAG GRID -->
                                <div id="hashtagGrid" class="hashtag-grid"></div>

                            </div>
                            
                            <!-- IDEA GENERATOR -->
                            <div class="ui-container" style="width: 750px; min-height: 600px;">
                                <div class="d-flex justify-content-between" style="align-items: center;">
                                    <h5><i class="fas fa-user" style="padding-right: 10px; font-size: 18px;"></i><strong>Viral AI Content Ideas</strong></h5>
                                    <button class="btn btn-light" onclick="generateIdeaCards()" style="border-radius:10px;font-weight:700;padding:5px 18px; color: white;">
                                        <i class="fas fa-redo-alt" style="padding-right: 7px;"></i>
                                        Refresh
                                    </button>
                                </div>
                                <hr style="margin-top: 7px;">
                                <!-- IDEA GRID -->
                                <div id="ideaGrid" class="idea-grid" style="height: 500px;"></div>
                            </div>
                        </div>
                    
                        <!-- CHAT -->
                        <div class="ui-container p-0" style="overflow:hidden; padding: 20px !important;">
                            <div>

                                <h5>
                                    <i class="fas fa-magic"
                                    style="padding-right: 10px; font-size: 18px;">
                                    </i>

                                    <strong>AI Content Assistant</strong>
                                </h5>
                            </div>

                            <hr>
                            <div style="flex:1;display:flex;flex-direction:column;">
                                <div id="chatBox"
                                    class="chat-box">
                                </div>
                                <div style="padding:18px 0px 0px 0px;background:white;border-top:1px solid #e5e7eb;">
                                    <div style=" display:flex; gap: 14px 14px; align-items:flex-end;">
                                        <div style="flex:1; position:relative;">
                                            <textarea id="postText" class="form-control" placeholder="Describe the content you want to create..."></textarea>
                                            <div class="ai-controls" style="position:absolute; bottom:13px; left:10px; display:flex; gap:8px; flex-wrap:wrap; width:calc(100% - 20px);">
                                                <select id="platformSelect" class="form-select form-select-sm" style="width:auto;min-width:150px;border-radius:10px;">
                                                    <option value="Instagram">Instagram</option>
                                                    <option value="Twitter/X">Twitter/X</option>
                                                    <option value="Facebook">Facebook</option>
                                                    <option value="TikTok">TikTok</option>
                                                    <option value="LinkedIn">LinkedIn</option>
                                                    <option value="Mastodon">Mastodon</option>
                                                    <option value="Threads">Threads</option>
                                                </select>
                                                <select id="toneSelect" class="form-select form-select-sm" style="width:auto;min-width:150px;border-radius:10px;">
                                                    <option value="Professional">Professional</option>
                                                    <option value="Trendy">Trendy</option>
                                                    <option value="Aggressive">Aggressive</option>
                                                    <option value="Luxury">Luxury</option>
                                                    <option value="Minimalist">Minimalist</option>
                                                    <option value="Funny">Funny</option>
                                                    <option value="Educational">Educational</option>
                                                    <option value="Authority">Authority</option>
                                                </select>
                                                <select id="purposeSelect" class="form-select form-select-sm" style="width:auto;min-width:190px;border-radius:10px;">
                                                    <option value="Engagement Mode">Engagement Mode</option>
                                                    <option value="Growth Mode">Growth Mode</option>
                                                    <option value="Controversy">Controversy</option>
                                                    <option value="Virality">Virality</option>
                                                    <option value="Brand Awareness">Brand Awareness</option>
                                                    <option value="Conversions">Conversions</option>
                                                    <option value="Community Building">Community Building</option>
                                                    <option value="Retention">Retention</option>
                                                </select>
                                                <select id="responseFormatSelect" class="form-select form-select-sm" style="width:auto;min-width:160px;border-radius:10px;">
                                                    <option value="Full Post">Full Post</option>
                                                    <option value="Caption">Caption</option>
                                                    <option value="Outline">Outline</option>
                                                    <option value="Reply">Reply Mode</option>
                                                </select>
                                            </div>
                                        </div>
                                        <!-- SEND BUTTON -->
                                        <button class="btn btn-primary" onclick="generateContentIdeas()" style=" width:98px; height:98px; border-radius:14px; flex-shrink:0; font-size: 20px;">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ui-container" style="max-width: 1220px;">
                            <h5>
                                <i class="fas fa-fire"
                                style="padding-right: 10px; font-size: 18px; margin-bottom: 15px;">
                                </i>
                                Top Performing Posts
                            </h5>

                            <div class="horizontal-scroll">

                                <?php if ($topPosts['http_code'] === 200 && !empty($topPosts['data']['data'])): ?>

                                    <?php foreach ($topPosts['data']['data'] as $post): ?>

                                        <?php
                                            $url = $post['permalink'] ?? $post['url'] ?? '';
                                            $embed = renderPostEmbed($url);

                                            // prevent clicking through inside embeds
                                            $embed = str_replace(
                                                '<a ',
                                                '<a onclick="event.preventDefault(); return false;" ',
                                                $embed
                                            );
                                        ?>

                                        <div class="post-card">

                                            <div class="card ui-container d-flex flex-column"
                                                style="padding:0px !important; overflow:hidden;">

                                                <div style="pointer-events:none;">
                                                    <?= $embed ?>
                                                </div>

                                                <div style="padding:14px; background:white; border-top:1px solid #edf0f7;">

                                                    <?php
                                                        $postContent = $post['content'] ?? '';
                                                        $postImages = $post['attachments'] ?? [];
                                                        $encodedImages = urlencode(json_encode($postImages));
                                                    ?>

                                                    <a href="post-new.php?source=<?= urlencode($url) ?>&content=<?= urlencode($postContent) ?>&images=<?= urlencode(json_encode($postImages)) ?>" class="btn btn-primary w-100" style="border-radius:10px; font-weight:700; height:44px; display:flex; align-items:center; justify-content:center; gap:8px;">
                                                        <i class="fas fa-plus"></i>
                                                        Reuse Post
                                                    </a>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div class="post-card">
                                        <div class="ui-container">
                                            <p class="text-warning">No top posts found.</p>
                                            <pre><?= htmlspecialchars($topPosts['raw'] ?? '') ?></pre>
                                        </div>
                                    </div>

                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        async function generateContentRoadmap()
        {
            const loading =
                document.getElementById(
                    "aiOverviewLoading"
                );

            const output =
                document.getElementById(
                    "overviewTyping"
                );

            loading.style.display = "block";
            output.innerHTML = "";

            try {

                const response = await fetch("", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        action: "content_roadmap",
                        top_posts: TOP_POSTS_TEXT
                    })
                });

                const data =
                    await response.json();

                loading.style.display = "none";

                if (!data.success) {

                    output.innerHTML =
                        "Failed to generate roadmap.";

                    return;
                }

                output.innerHTML =
                    data.overview;

            } catch (e) {

                loading.style.display = "none";

                output.innerHTML =
                    "Failed to generate roadmap.";
            }
        }
        
        async function generateTrendingHashtags() {

            const grid = document.getElementById("hashtagGrid");

            /**
             * TOPIC
             */
            const topic =
                document.getElementById("hashtagTopic")
                ?.value
                ?.trim() || "";

            /**
             * LOADING STATE
             */
            grid.style.display = "block";
            grid.style.gridTemplateColumns = "none";

            grid.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center py-5"
                    style="width:100%; text-align:center; height: 340px;" >

                    <div style="
                        font-size:15px;
                        font-weight:bold;
                        color:#9a97a7;
                        margin-top:12px;
                    ">
                        <i class="fas fa-spinner fa-spin"
                        style="font-size:20px;color:#9a97a7;">
                        </i>

                        <span style="padding-left:10px;">
                            Generating trending hashtags...
                        </span>
                    </div>

                </div>
            `;

            try {

                const response = await fetch("", {
                    method: "POST",

                    headers: {
                        "Content-Type": "application/json"
                    },

                    body: JSON.stringify({
                        type: "content_generation",
                        preset_key: "trending_hashtags",
                        topic: topic,
                        metrics: AI_METRICS
                    })
                });

                const data = await response.json();

                if (!data.success) {

                    grid.innerHTML = `
                        <div class="alert alert-danger">
                            Failed to generate hashtags
                        </div>
                    `;

                    return;
                }

                const hashtags = data.hashtags || [];

                /**
                 * ENABLE GRID
                 */
                grid.style.display = "grid";

                grid.style.gridTemplateColumns =
                    "repeat(auto-fill,minmax(180px,1fr))";

                grid.innerHTML = "";

                hashtags.forEach(tag => {

                    const card = document.createElement("div");

                    card.className = "hashtag-card";

                    card.innerHTML = `
                        <div class="hashtag-header">

                            <div class="hashtag-text">
                                ${tag.hashtag}
                            </div>

                            <div class="copy-icon">
                                <i class="fas fa-copy" style="font-size: 16px;"></i>
                            </div>

                        </div>

                        <div class="hashtag-meta">
                            ${tag.category}
                        </div>
                    `;

                    card.onclick = () => {

                        navigator.clipboard.writeText(tag.hashtag);

                        const copyBtn =
                            card.querySelector(".copy-icon");

                        copyBtn.innerHTML =
                            `<i class="fas fa-check"></i>`;

                        setTimeout(() => {
                            copyBtn.innerHTML =
                                `<i class="fas fa-copy"></i>`;
                        }, 1500);
                    };

                    grid.appendChild(card);
                });

            } catch (e) {

                console.error(e);

                grid.innerHTML = `
                    <div class="alert alert-danger">
                        Error generating hashtags
                    </div>
                `;
            }
        }

        let lastAIResponse = "";

        document.addEventListener("click", function(e){

            const btn = e.target.closest(".use-idea-btn");

            if(!btn) return;

            const description =
                decodeURIComponent(
                    btn.dataset.description || ""
                );

            console.log("🔥 CLICKED IDEA");
            console.log(description);

            useIdea(description);
        });

        function useIdea(description){

            console.log("🎯 useIdea() called");

            if(!description){
                return;
            }

            document.getElementById("postText").value =
                description;

            document.getElementById("chatBox")
                .scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });

            generateContentIdeas(
                description,
                null,
                true
            );
        }
        
        async function generateIdeaCards(){

            const grid = document.getElementById("ideaGrid");

            grid.innerHTML = `
                <div class="w-100 d-flex align-items-center justify-content-center py-5 gap-3">
                    <i class="fas fa-spinner fa-spin"
                    style="font-size:20px;color:#9a97a7;">
                    </i>

                    <div style="font-size:15px;font-weight:500;color:#9a97a7; margin-left: -8px; font-weight: bold;">
                        Generating ideas...
                    </div>
                </div>
            `;

            try{

                const platform =
                    document.getElementById("platformSelect").value;

                const tone =
                    document.getElementById("toneSelect").value;

                const purpose =
                    document.getElementById("purposeSelect").value;

                const res = await fetch(window.location.href,{
                    method:"POST",
                    headers:{
                        "Content-Type":"application/json"
                    },
                    body:JSON.stringify({
                        type:"content_generation",
                        preset_key:"trending_ideas",
                        platform,
                        tone,
                        purpose,
                        metrics:AI_METRICS
                    })
                });

                const data = await res.json();

                if(!data.success){

                    grid.innerHTML = `
                        <div class="alert alert-danger">
                            Failed generating ideas.
                        </div>
                    `;

                    return;
                }

                let html = "";

                data.ideas.forEach((idea, index) => {

                    html += `
                        <div class="idea-card"
                            style="animation-delay:${index * 0.08}s;">

                            <div class="idea-title">
                                ${idea.title}
                            </div>

                            <div class="idea-description">
                                ${idea.description}
                            </div>

                            <div>
                                <div class="idea-meta">
                                    <div class="idea-badge">
                                        ${idea.platform}
                                    </div>

                                    <div class="idea-badge">
                                        ${idea.tag || "Trending"}
                                    </div>
                                </div>

                                <button class="use-btn use-idea-btn" data-description="${encodeURIComponent(idea.description)}">
                                    Use This
                                </button>
                            </div>

                        </div>
                    `;
                });

                grid.innerHTML = html;

            }catch(err){

                console.error(err);

                grid.innerHTML = `
                    <div class="alert alert-danger">
                        Failed generating ideas.
                    </div>
                `;
            }
        }

        const AI_METRICS =
            <?= json_encode($aiPayload) ?>;

        /**
         * =====================================================
         * INTRO
         * =====================================================
         */
        document.addEventListener("DOMContentLoaded", () => {

            appendAIMessage(`
                <p style="margin: 0 !important; color: black;">I'm your <b>Content Studio AI Assistant</b>! Select an idea below or create your own custom request.</p>
            `);

            generateIdeaCards();
            generateTrendingHashtags();
            generateContentRoadmap();
        });

        /**
         * =====================================================
         * HELPERS
         * =====================================================
         */
        function appendUserMessage(text){

            const chatBox =
                document.getElementById(
                    "chatBox"
                );

            chatBox.innerHTML += `
                <div class="msg-row">
                    <div class="msg user">
                        ${text}
                    </div>
                </div>
            `;

            chatBox.scrollTop =
                chatBox.scrollHeight;
        }

        function decodeHTMLEntities(str) {
            const textarea = document.createElement("textarea");
            textarea.innerHTML = str;
            return textarea.value;
        }

        function appendAIMessage(data){

            const chatBox =
                document.getElementById("chatBox");

            let html = normalizeAIResponse(data);

            html = decodeHTMLEntities(html);


            let imageHtml = "";
            let imageUrl = "";

            if (
                typeof data === "object" &&
                data.image
            ) {
                imageUrl = data.image;
            }

            /**
             * ==========================================
             * REUSE BUTTON
             * ==========================================
             */
            let reuseButton = "";

            const currentFormat =
                document.getElementById(
                    "responseFormatSelect"
                )?.value;

            if (
                typeof data === "object" &&
                currentFormat === "Full Post"
            ) {

                const encodedContent = encodeURIComponent(data.raw_text || "");

                const encodedImage =
                    encodeURIComponent(
                        JSON.stringify([imageUrl])
                    );

                reuseButton = `
                    <div style="margin-top:16px;">
                        <a
                            href="post-new.php?source=ai_generated&content=${encodedContent}&images=${encodedImage}"
                            class="btn btn-primary w-100"
                            style="
                                border-radius:12px;
                                height:46px;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                gap:8px;
                                font-weight:700;
                            "
                        >
                            <i class="fas fa-plus"></i>
                            Create from this
                        </a>
                    </div>
                `;
            }
            
            const clean = DOMPurify.sanitize(`
                <div class="ai-output">
                    ${html}
                    ${imageHtml}
                    ${reuseButton}
                </div>
            `);

            chatBox.innerHTML += `
                <div class="msg-row">
                    <div class="msg ai pretty-ai-response">
                        ${clean}
                    </div>
                </div>
            `;

            chatBox.scrollTop = chatBox.scrollHeight;
        }

        function normalizeAIResponse(data){

            if(!data) return "<p>No response generated.</p>";

            // trending structured output (ALWAYS SAME STYLE)
            if(data.type === "trending_ideas" && Array.isArray(data.ideas)){

                let html = `<div class="idea-wrapper">`;

                data.ideas.forEach(idea => {

                    html += `
                        <div class="idea-card">

                            <div class="idea-title">
                                ${idea.title ?? ""}
                            </div>

                            <div class="idea-meta">
                                <div class="idea-badge">
                                    ${idea.platform ?? "Unknown"}
                                </div>

                                <div class="idea-badge">
                                    ${idea.tag ?? "Trending"}
                                </div>
                            </div>

                            <div class="idea-description">
                                ${idea.description ?? ""}
                            </div>

                            <button
                                class="btn btn-primary btn-sm mt-3 use-idea-btn"
                                data-idea="${encodeURIComponent(
                                    JSON.stringify(idea)
                                )}"
                            >
                                Use This
                            </button>

                        </div>
                    `;
                });

                html += `</div>`;
                return html;
            }

            // EVERYTHING ELSE becomes plain text wrapper (no AI styling differences)
            const text = (typeof data === "string")
                ? data
                : (data.summary || JSON.stringify(data));

            return `<div class="ai-card">${text}</div>`;
        }

        /**
         * =====================================================
         * PRESET
         * =====================================================
         */
        async function sendPresetPrompt(
            presetKey,
            promptText
        ){

            document.getElementById(
                "postText"
            ).value = promptText;

            await generateContentIdeas(
                promptText,
                presetKey, false
            );
        }

        /**
         * =====================================================
         * LOADER
         * =====================================================
         */
        function showTypingLoader(){
            appendAIMessage(`
                <div class="typing-loader">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            `);
        }

        /**
         * =====================================================
         * TRENDING IDEAS
         * =====================================================
         */
        async function generateTrendingIdeas(){

            appendUserMessage(
                "Generate trending content ideas."
            );

            showTypingLoader();

            try{

                const res =
                    await fetch(
                        window.location.href,
                        {
                            method:"POST",

                            headers:{
                                "Content-Type":
                                    "application/json"
                            },

                            body: JSON.stringify({

                                type:
                                    "content_generation",

                                preset_key:
                                    "trending_ideas",

                                metrics:
                                    AI_METRICS
                            })
                        }
                    );

                const raw =
                    await res.text();

                console.log("RAW RESPONSE:", raw);

                const data =
                    JSON.parse(raw);

                if(data.summary){

                    data.summary = data.summary
                        .replace(/^```html\s*/i, "")
                        .replace(/^```\s*/i, "")
                        .replace(/```$/i, "")
                        .trim();
                }

                console.log(
                    "PARSED RESPONSE:",
                    data
                );

                removeLastAIMessage();

                if(!data.success){

                    appendAIMessage(`
                        <span class='text-danger'>
                            Failed generating trending ideas.
                        </span>
                    `);

                    return;
                }

                appendAIMessage(
                    normalizeAIResponse(data)
                );

            }catch(err){

                removeLastAIMessage();

                appendAIMessage(`
                    <span class='text-danger'>
                        Failed generating trending ideas.
                    </span>
                `);

                console.error(err);
            }
        }

        /**
         * =====================================================
         * USE TRENDING IDEA
         * =====================================================
         */
        async function useTrendingIdea(
            description
        ){

            document.getElementById(
                "postText"
            ).value = description;

            await generateContentIdeas(
                description
            );
        }

        /**
         * =====================================================
         * REMOVE LAST AI MESSAGE
         * =====================================================
         */
        function removeLastAIMessage(){

            const rows =
                document.querySelectorAll(
                    ".msg-row"
                );

            if(rows.length){

                rows[
                    rows.length - 1
                ].remove();
            }
        }

        /**
         * =====================================================
         * MAIN AI
         * =====================================================
         */
        async function generateContentIdeas(forcedPrompt = null, presetKey = null, shouldScroll = false){

            const input =
                document.getElementById(
                    "postText"
                );

            let finalPrompt =
                (
                    forcedPrompt
                    || input.value
                ).trim();
            
            const responseFormat =
                document.getElementById(
                    "responseFormatSelect"
                ).value;

            if(!finalPrompt) return;

                        if(shouldScroll){

                document.getElementById("chatBox")
                    .scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
            }

            appendUserMessage(finalPrompt);

            showTypingLoader();

            try{
                const platform =
                    document.getElementById(
                        "platformSelect"
                    ).value;

                const tone =
                    document.getElementById(
                        "toneSelect"
                    ).value;

                const purpose =
                    document.getElementById(
                        "purposeSelect"
                    ).value;
                    
                const res =
                    await fetch(
                        window.location.href,
                        {
                            method:"POST",

                            headers:{
                                "Content-Type":
                                    "application/json"
                            },

                            body: JSON.stringify({
                                platform: platform,
                                tone: tone,
                                purpose: purpose,
                                response_format: responseFormat,
                                type:
                                    "content_generation",

                                text:
                                    finalPrompt,

                                preset_key:
                                    presetKey,

                                metrics:
                                    AI_METRICS,

                                previous_ai: lastAIResponse
                            })
                        }
                    );

                const raw =
                    await res.text();

                console.log("RAW RESPONSE:", raw);

                const data =
                    JSON.parse(raw);

                if(data.summary){

                    data.summary = data.summary
                        .replace(/^```html\s*/i, "")
                        .replace(/^```\s*/i, "")
                        .replace(/```$/i, "")
                        .replace(/<br\s*\/?>/gi, "")
                        .replace(/margin:\s*[^;"]+;?/gi, "")   // 🔥 remove inline spacing
                        .trim();
                }
                
                console.log(
                    "PARSED RESPONSE:",
                    data
                );

                removeLastAIMessage();

                let finalHTML = data.summary || "";
                
                finalHTML = finalHTML
                .replace(/^\s+/gm, "")   // remove leading spaces per line
                .replace(/\n{2,}/g, "\n") // optional: collapse extra blank lines
                .trim();
                /**
                 * Replace placeholder with actual AI image
                 */
                if (data.image) {

                    finalHTML = finalHTML.replace(

                        /<div[^>]*class=["'][^"']*ai-image-placeholder[^"']*["'][^>]*>[\s\S]*?<\/div>/i,

                        `
                        <div class="generated-image-wrapper">
                            <img
                                src="${data.image}"
                                class="generated-ai-image"
                            >
                        </div>
                        `
                    );
                }
                
                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = finalHTML;

                /**
                * Extract ONLY post content
                */
                const postContentEl =
                    tempDiv.querySelector(".post-content");

                let cleanPostText = "";

                if (postContentEl) {

                    cleanPostText =
                        postContentEl.innerText.trim();

                } else {

                    cleanPostText =
                        tempDiv.innerText.trim();
                }

                lastAIResponse = finalHTML;

                finalPrompt += lastAIResponse;

                appendAIMessage({
                    summary: finalHTML,
                    image: data.image || null,
                    raw_text: cleanPostText
                });

                console.log("🧠 SENT PROMPT:\n", finalPrompt);

                input.value = "";

            }catch(err){

                removeLastAIMessage();

                appendAIMessage(`
                    <span class='text-danger'>
                        AI request failed.
                    </span>
                `);

                console.error(err);
            }
        }

        /**
         * =====================================================
         * GLOBAL CLICK HANDLER (TRENDING IDEAS)
         * =====================================================
         */
        document.addEventListener("click", function (e) {

            const btn = e.target.closest(".use-idea-btn");
            if (!btn) return;

            const raw =
                btn.getAttribute("data-idea");

            let idea = {};

            try {

                idea = JSON.parse(
                    decodeURIComponent(raw || "{}")
                );

            } catch (e) {
                console.error("Invalid idea payload", e);
                return;
            }

            const description = idea.description || "";

            if (!description) return;

            document.getElementById("postText").value = description;

            //generateContentIdeas(description);
            useIdea(description);
        });

        const copyBtn = card.querySelector(".copy-icon");

        copyBtn.addEventListener("click", (e) => {

            e.stopPropagation();

            navigator.clipboard.writeText(tag.hashtag);

            copyBtn.innerHTML =
                `<i class="fas fa-check"></i>`;

            setTimeout(() => {
                copyBtn.innerHTML =
                    `<i class="fas fa-copy"></i>`;
            }, 1500);
        });
    </script>

</body>
</html>