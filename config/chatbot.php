<?php

return [
    // Not a credential (unlike config/services.php's provider keys) — just the
    // display name shown in the widget and used in the system instruction.
    'persona_name' => env('CHATBOT_NAME', 'Noor'),

    // Which AI provider actually answers — see App\Services\Chatbot\ChatbotProvider and its
    // AppServiceProvider binding. 'groq' (OpenAI-compatible, generous free tier) or 'gemini'
    // (Google's native API) — both implementations stay in the codebase either way, so
    // switching back is just this one env value, no code change.
    'provider' => env('CHATBOT_PROVIDER', 'groq'),

    // Shown first in the widget's language picker, and used when the visitor hasn't picked one.
    'default_language' => 'en',

    /*
    | Languages the assistant chats in (the widget's "Choose your language"). The AI writes its own
    | replies in the chosen language; `strings` are the few lines the widget / server say without it.
    | `name` is the English name (given to the AI), `native` what the picker shows, `speech` the
    | browser voice locale for the mic and read-aloud, `dir` the text direction.
    | {name} in `welcome` is the assistant's persona name. `switch_offer` (shown when the visitor
    | starts typing in this language) and `switched` are written in, and about, this language.
    */
    'languages' => [
        'en' => [
            'name' => 'English', 'native' => 'English', 'speech' => 'en-US', 'dir' => 'ltr',
            'strings' => [
                'welcome' => 'Welcome to MW Realty! I\'m {name}, your personal property finder. Tell me what you\'re looking for, and I\'ll pull up real matches for you in seconds.',
                'placeholder' => 'Ask about properties or the buying process…',
                'switch_offer' => 'It looks like you\'re writing in English. Continue in English?',
                'switched' => 'Sure — I\'ll reply in English from now on.',
                'matches' => 'Here are the closest matches I found:',
                'no_matches' => 'I couldn\'t find any properties matching that — try broadening your search.',
                'error' => 'Sorry, I\'m having trouble answering right now — please try again in a moment, or use the enquiry form on any listing.',
                'unavailable' => 'The AI assistant isn\'t available yet — please use the enquiry form on any listing instead.',
            ],
        ],
        'ar' => [
            'name' => 'Arabic', 'native' => 'العربية', 'speech' => 'ar-AE', 'dir' => 'rtl',
            'strings' => [
                'welcome' => 'مرحبًا بك في MW Realty! أنا {name}، مساعدك الشخصي للبحث عن العقارات. أخبرني بما تبحث عنه، وسأعرض لك عقارات حقيقية مطابقة خلال ثوانٍ.',
                'placeholder' => 'اسأل عن العقارات أو عن خطوات الشراء…',
                'switch_offer' => 'يبدو أنك تكتب بالعربية. هل تريد المتابعة بالعربية؟',
                'switched' => 'بالتأكيد — سأرد عليك بالعربية من الآن.',
                'matches' => 'إليك أقرب العقارات المطابقة التي وجدتها:',
                'no_matches' => 'لم أجد عقارات تطابق طلبك — جرّب توسيع نطاق البحث.',
                'error' => 'عذرًا، أواجه مشكلة في الرد الآن — حاول مرة أخرى بعد قليل، أو استخدم نموذج الاستفسار في أي إعلان.',
                'unavailable' => 'المساعد الذكي غير متاح بعد — يرجى استخدام نموذج الاستفسار في أي إعلان.',
            ],
        ],
        'hi' => [
            'name' => 'Hindi', 'native' => 'हिन्दी', 'speech' => 'hi-IN', 'dir' => 'ltr',
            'strings' => [
                'welcome' => 'MW Realty में आपका स्वागत है! मैं {name} हूँ, आपका निजी प्रॉपर्टी खोज सहायक। बताइए आप क्या ढूँढ रहे हैं, और मैं कुछ ही सेकंड में असली मिलती-जुलती प्रॉपर्टी दिखाऊँगा।',
                'placeholder' => 'प्रॉपर्टी या खरीदने की प्रक्रिया के बारे में पूछें…',
                'switch_offer' => 'लगता है आप हिन्दी में लिख रहे हैं। क्या हिन्दी में जारी रखें?',
                'switched' => 'ज़रूर — अब से मैं हिन्दी में जवाब दूँगा।',
                'matches' => 'ये रहीं सबसे क़रीबी मिलती-जुलती प्रॉपर्टी:',
                'no_matches' => 'मुझे इससे मेल खाती कोई प्रॉपर्टी नहीं मिली — अपनी खोज थोड़ी व्यापक करके देखें।',
                'error' => 'माफ़ कीजिए, अभी जवाब देने में दिक्कत हो रही है — थोड़ी देर बाद फिर कोशिश करें, या किसी भी लिस्टिंग पर पूछताछ फ़ॉर्म इस्तेमाल करें।',
                'unavailable' => 'AI सहायक अभी उपलब्ध नहीं है — कृपया किसी भी लिस्टिंग पर पूछताछ फ़ॉर्म इस्तेमाल करें।',
            ],
        ],
        'ur' => [
            'name' => 'Urdu', 'native' => 'اردو', 'speech' => 'ur-PK', 'dir' => 'rtl',
            'strings' => [
                'welcome' => 'MW Realty میں خوش آمدید! میں {name} ہوں، آپ کا ذاتی پراپرٹی تلاش معاون۔ بتائیں آپ کیا ڈھونڈ رہے ہیں، اور میں چند سیکنڈ میں حقیقی ملتی جلتی پراپرٹیز دکھاؤں گا۔',
                'placeholder' => 'پراپرٹیز یا خریداری کے طریقے کے بارے میں پوچھیں…',
                'switch_offer' => 'لگتا ہے آپ اردو میں لکھ رہے ہیں۔ کیا اردو میں جاری رکھیں؟',
                'switched' => 'ضرور — اب سے میں اردو میں جواب دوں گا۔',
                'matches' => 'یہ رہیں سب سے قریب ترین ملتی جلتی پراپرٹیز:',
                'no_matches' => 'مجھے اس سے ملتی کوئی پراپرٹی نہیں ملی — اپنی تلاش کو تھوڑا وسیع کر کے دیکھیں۔',
                'error' => 'معذرت، ابھی جواب دینے میں مسئلہ ہو رہا ہے — تھوڑی دیر بعد دوبارہ کوشش کریں، یا کسی بھی لسٹنگ پر انکوائری فارم استعمال کریں۔',
                'unavailable' => 'AI معاون ابھی دستیاب نہیں — براہ کرم کسی بھی لسٹنگ پر انکوائری فارم استعمال کریں۔',
            ],
        ],
        'fa' => [
            'name' => 'Persian', 'native' => 'فارسی', 'speech' => 'fa-IR', 'dir' => 'rtl',
            'strings' => [
                'welcome' => 'به MW Realty خوش آمدید! من {name} هستم، دستیار شخصی شما برای پیدا کردن ملک. بگویید دنبال چه هستید تا در چند ثانیه ملک‌های واقعی و مناسب را نشانتان بدهم.',
                'placeholder' => 'درباره‌ی املاک یا مراحل خرید بپرسید…',
                'switch_offer' => 'به نظر می‌رسد فارسی می‌نویسید. به فارسی ادامه دهیم؟',
                'switched' => 'حتماً — از این به بعد به فارسی پاسخ می‌دهم.',
                'matches' => 'نزدیک‌ترین ملک‌هایی که پیدا کردم:',
                'no_matches' => 'ملکی مطابق درخواست شما پیدا نکردم — جستجو را کمی گسترده‌تر کنید.',
                'error' => 'متأسفم، الان در پاسخ دادن مشکل دارم — کمی بعد دوباره امتحان کنید یا از فرم استعلام در هر آگهی استفاده کنید.',
                'unavailable' => 'دستیار هوشمند هنوز در دسترس نیست — لطفاً از فرم استعلام در هر آگهی استفاده کنید.',
            ],
        ],
        'ru' => [
            'name' => 'Russian', 'native' => 'Русский', 'speech' => 'ru-RU', 'dir' => 'ltr',
            'strings' => [
                'welcome' => 'Добро пожаловать в MW Realty! Я {name}, ваш личный помощник по поиску недвижимости. Расскажите, что вы ищете, и я за секунды подберу реальные варианты.',
                'placeholder' => 'Спросите о недвижимости или о процессе покупки…',
                'switch_offer' => 'Похоже, вы пишете по-русски. Продолжим по-русски?',
                'switched' => 'Хорошо — теперь я буду отвечать по-русски.',
                'matches' => 'Вот наиболее подходящие варианты, которые я нашёл:',
                'no_matches' => 'Я не нашёл подходящих объектов — попробуйте расширить условия поиска.',
                'error' => 'Извините, сейчас не получается ответить — попробуйте чуть позже или воспользуйтесь формой запроса на странице любого объекта.',
                'unavailable' => 'ИИ-помощник пока недоступен — воспользуйтесь формой запроса на странице любого объекта.',
            ],
        ],
        'zh' => [
            'name' => 'Simplified Chinese', 'native' => '简体中文', 'speech' => 'zh-CN', 'dir' => 'ltr',
            'strings' => [
                'welcome' => '欢迎来到 MW Realty！我是 {name}，您的专属找房助手。告诉我您想要什么样的房产，我会在几秒内为您找到真实的匹配房源。',
                'placeholder' => '咨询房源或购房流程…',
                'switch_offer' => '您似乎在用中文输入。要继续使用中文吗？',
                'switched' => '好的——接下来我会用中文回复您。',
                'matches' => '这是我找到的最匹配的房源：',
                'no_matches' => '没有找到符合条件的房源——请尝试放宽搜索条件。',
                'error' => '抱歉，暂时无法回复——请稍后再试，或在任一房源页面使用咨询表单。',
                'unavailable' => 'AI 助手暂不可用——请在任一房源页面使用咨询表单。',
            ],
        ],
        'fr' => [
            'name' => 'French', 'native' => 'Français', 'speech' => 'fr-FR', 'dir' => 'ltr',
            'strings' => [
                'welcome' => 'Bienvenue chez MW Realty ! Je suis {name}, votre assistant personnel de recherche immobilière. Dites-moi ce que vous cherchez et je vous montre de vrais biens correspondants en quelques secondes.',
                'placeholder' => 'Posez une question sur les biens ou l\'achat…',
                'switch_offer' => 'On dirait que vous écrivez en français. Continuer en français ?',
                'switched' => 'Entendu — je vous répondrai en français désormais.',
                'matches' => 'Voici les biens les plus proches de votre recherche :',
                'no_matches' => 'Je n\'ai trouvé aucun bien correspondant — essayez d\'élargir votre recherche.',
                'error' => 'Désolé, je n\'arrive pas à répondre pour le moment — réessayez dans un instant, ou utilisez le formulaire de demande d\'une annonce.',
                'unavailable' => 'L\'assistant IA n\'est pas encore disponible — utilisez le formulaire de demande d\'une annonce.',
            ],
        ],
        'de' => [
            'name' => 'German', 'native' => 'Deutsch', 'speech' => 'de-DE', 'dir' => 'ltr',
            'strings' => [
                'welcome' => 'Willkommen bei MW Realty! Ich bin {name}, Ihr persönlicher Assistent für die Immobiliensuche. Sagen Sie mir, wonach Sie suchen, und ich zeige Ihnen in Sekunden passende, echte Angebote.',
                'placeholder' => 'Fragen Sie nach Immobilien oder dem Kaufablauf…',
                'switch_offer' => 'Sie schreiben anscheinend auf Deutsch. Auf Deutsch weitermachen?',
                'switched' => 'Gern — ab jetzt antworte ich auf Deutsch.',
                'matches' => 'Das sind die passendsten Angebote, die ich gefunden habe:',
                'no_matches' => 'Ich habe keine passenden Immobilien gefunden — versuchen Sie, die Suche zu erweitern.',
                'error' => 'Entschuldigung, ich kann gerade nicht antworten — bitte versuchen Sie es gleich noch einmal oder nutzen Sie das Anfrageformular eines Inserats.',
                'unavailable' => 'Der KI-Assistent ist noch nicht verfügbar — bitte nutzen Sie das Anfrageformular eines Inserats.',
            ],
        ],
        'es' => [
            'name' => 'Spanish', 'native' => 'Español', 'speech' => 'es-ES', 'dir' => 'ltr',
            'strings' => [
                'welcome' => '¡Bienvenido a MW Realty! Soy {name}, tu asistente personal para buscar propiedades. Cuéntame qué buscas y te mostraré opciones reales en segundos.',
                'placeholder' => 'Pregunta por propiedades o por el proceso de compra…',
                'switch_offer' => 'Parece que escribes en español. ¿Seguimos en español?',
                'switched' => 'Claro — a partir de ahora responderé en español.',
                'matches' => 'Estas son las propiedades que mejor encajan:',
                'no_matches' => 'No encontré propiedades que coincidan — prueba a ampliar la búsqueda.',
                'error' => 'Lo siento, ahora mismo no puedo responder — inténtalo de nuevo en un momento o usa el formulario de consulta de cualquier anuncio.',
                'unavailable' => 'El asistente de IA aún no está disponible — usa el formulario de consulta de cualquier anuncio.',
            ],
        ],
    ],
];
