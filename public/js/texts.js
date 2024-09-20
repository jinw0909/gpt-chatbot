const texts = {
    kr: {
        connectedTime: '접속 시간',
        charge: '충전',
        send: '전송',
        openChat: '채팅창 열기',
        closeChat: '채팅창 닫기',
        placeHolder: '메시지를 입력해 주세요...',
        generating: "답변 생성중...",
        headerText: ['안녕하세요.', 'Goya Chat AI에 오신 것을 환영합니다.', 'Goya Chat AI는 사용료가 부과되는 유료 서비스입니다.', 'Chat AI 질문을 클릭해 주세요.'],
        askQuestion: 'Chat AI 질문하기',
        initialQuery: ['진입하기 좋은 암호 화폐 추천', '암호 화폐 시장 동향', '고야 스코어란?'],
        timezone: 'Asia/Seoul', // Added timezone for Korean
    },
    jp: {
        connectedTime: '接続時間',
        charge: 'チャージ',
        send: '送信',
        openChat: 'チャットを開く',
        closeChat: 'チャットを閉じる',
        placeHolder: 'メッセージを入力してください...',
        generating: "回答を生成中...",
        headerText: ['こんにちは。', 'Goya Chat AIへようこそ。', 'Goya Chat AIは有料サービスです。', 'Chat AIの質問をクリックしてください。'],
        askQuestion: 'Chat AIに質問する',
        initialQuery: ['エントリーに適した暗号通貨のおすすめ', '暗号通貨市場の動向', 'Goyaスコアとは？'],
        timezone: 'Asia/Tokyo', // Added timezone for Japanese
    },
    en: {
        connectedTime: 'Connected Time',
        charge: 'Charge',
        send: 'Send',
        openChat: 'Open Chat',
        closeChat: 'Close Chat',
        placeHolder: 'Please enter a message...',
        generating: "Generating response...",
        headerText: ['Hello.', 'Welcome to Goya Chat AI.', 'Goya Chat AI is a paid service.', 'Please click on a Chat AI question.'],
        askQuestion: 'Ask Chat AI',
        initialQuery: ['Recommended cryptocurrencies to enter', 'Cryptocurrency market trends', 'What is the Goya Score?'],
        timezone: 'UTC', // Default to UTC for English
    }
};

const timeUnits = {
    kr: {
        hour: '시간 ',
        hours: '시간 ',
        minute: '분 ',
        minutes: '분 ',
        articles: '전 (작성 시간)',
        analysis: '전 (마지막 스코어/가격)',
        recommend: '전 (마지막 가격 시그널)'
    },
    jp: {
        hour: '時間',
        hours: '時間',
        minute: '分',
        minutes: '分',
        articles: '前（作成時間）',
        analysis: '前（最後のスコア/価格）',
        recommend: '前（最後の価格シグナル）'
    },
    en: {
        hour: 'hour ',
        hours: 'hours ',
        minute: 'minute ',
        minutes: 'minutes ',
        articles: 'ago (Time Written)',
        analysis: 'ago (Last Score/Price)',
        recommend: 'ago (Last Price Signal)'
    }
};

const timeZoneAbbreviations = {
    'Asia/Seoul': 'KST',
    'Asia/Tokyo': 'JST',
    'UTC': 'UTC'
};

let selectedLanguage = 'kr'; // Default selected language

function handleLanguageChange(event) {
    selectedLanguage = event.target.value;
    console.log("Selected Language:", selectedLanguage);
    initText();
    changeText();
}

// Attach the event listener to the select element
document.getElementById('language-select').addEventListener('change', handleLanguageChange);

// Function to set login time based on selected language's timezone
// function setLoginTime() {
//     const langText = texts[selectedLanguage];
//     const timeZone = langText.timezone || 'UTC'; // Use the selected language's timezone or default to UTC
//
//     // Get current time in the selected timezone, excluding seconds
//     const currentTime = new Date().toLocaleString('en-US', {
//         timeZone: timeZone,
//         hour12: false,
//         year: 'numeric',
//         month: '2-digit',
//         day: '2-digit',
//         hour: '2-digit',
//         minute: '2-digit',
//     });
//
//     // Get the abbreviation for the selected timezone
//     const timeZoneAbbreviation = timeZoneAbbreviations[timeZone] || timeZone; // Default to timezone if no abbreviation
//
//     // Set the formatted time in the login-time element
//     const loginTimeSpan = document.getElementById('login-time');
//     loginTimeSpan.textContent = `${currentTime} ${timeZoneAbbreviation}`;
// }

function initText() {
    console.log("changeText()");
    const langText = texts[selectedLanguage];

    // Get all elements that need to be changed
    const chargeText = document.getElementById('add-button');
    const connectTimeText = document.getElementById('connect-time');
    const headerTextSpans = document.querySelectorAll('#header-text span');
    const askQuestionText = document.getElementById('ask-question');
    const initialQueryElems = document.querySelectorAll('#initial-query p');
    const placeHolderText = document.getElementById('message-input');
    const openChatText = document.getElementById('input-open');
    const closeChatText = document.getElementById('input-close');

    // Update the text content of the elements
    chargeText.textContent = langText.charge;
    // connectTimeText.textContent = langText.connectedTime;
    headerTextSpans.forEach((span, index) => {
        if (langText.headerText[index]) {
            span.textContent = langText.headerText[index];
        }
    });
    askQuestionText.textContent = langText.askQuestion;
    initialQueryElems.forEach((p, index) => {
        if (langText.initialQuery[index]) {
            p.textContent = langText.initialQuery[index];
        }
    });
    placeHolderText.placeholder = langText.placeHolder;
    openChatText.textContent = langText.openChat;
    closeChatText.textContent = langText.closeChat;

    // Set login time based on selected language
    // setLoginTime();
}

// Function to map class names to keys in the texts object
function getClassToKeyMap() {
    return {
        'recommend-crypto': 'recommendCrypto',
        'market-trend': 'marketTrend',
        'major-news': 'majorNews',
        'about-goya': 'aboutGoya',
        'other-news': 'otherNews',
        'analyze-btc': 'analyzeBtc',
        'explain-symbol': 'explainSymbol',
        'analyze-symbol': 'analyzeSymbol',
        'analyze-symbol-month': 'analyzeSymbolMonth',
        'other-crypto': 'otherCrypto',
        'explain-criteria': 'explainCriteria'
    };
}

// New function to change text for all elements with specific classes
function changeText() {
    const langText = texts[selectedLanguage];
    const classToKeyMap = getClassToKeyMap();

    // Loop over each key in the map and update elements with the corresponding class
    for (const className in classToKeyMap) {
        const key = classToKeyMap[className];
        const elements = document.querySelectorAll(`.${className}`);

        elements.forEach(element => {
            if (langText[key]) {
                // Handle special cases for keys that require symbol name prepending
                if (key === 'explainSymbol' || key === 'analyzeSymbol' || key === 'analyzeSymbolMonth') {
                    const symbolMatch = element.textContent.match(/^[^\s]+/); // Assumes the symbol is the first word
                    const symbol = symbolMatch ? symbolMatch[0] : '';
                    element.textContent = `${symbol}${langText[key]}`;
                } else {
                    element.textContent = langText[key];
                }
            }
        });
    }

    // Set login time based on selected language
    // setLoginTime();
}

// Call initText initially to set up the text based on the default selected language
document.addEventListener('DOMContentLoaded', initText);

// const texts = {
//     kr: {
//         connectedTime: '접속 시간',
//         charge: '충전',
//         send: '전송',
//         openChat: '채팅창 열기',
//         closeChat: '채팅창 닫기',
//         placeHolder: '메시지를 입력해 주세요...',
//         generating: "답변 생성중...",
//         headerText: ['안녕하세요.', 'Goya Chat AI에 오신 것을 환영합니다.', 'Goya Chat AI는 사용료가 부과되는 유료 서비스입니다.', 'Chat AI 질문을 클릭해 주세요.'],
//         askQuestion: 'Chat AI 질문하기',
//         initialQuery: ['진입하기 좋은 암호 화폐 추천', '암호 화폐 시장 동향', '고야 스코어란?'],
//         otherNews: '다른 암호 화폐 뉴스',
//         analyzeBtc: '비트코인 스코어 및 가격 분석',
//         recommendCrypto: '진입 하기 좋은 암호 화폐 추천',
//         marketTrend: '암호 화폐 시장 전망',
//         majorNews: '암호 화폐 관련 주요 뉴스',
//         aboutGoya: '고야 스코어란?',
//         explainSymbol: '에 대해 알려줘',
//         analyzeSymbol: ' 스코어 및 가격 분석',
//         analyzeSymbolMonth: ' 한 달 간 스코어 및 가격 분석',
//         otherCrypto: '다른 암호 화폐 추천',
//         explainCriteria: '추천 기준에 대해 알려줘'
//     },
//     jp: {
//         connectedTime: '接続時間',
//         charge: 'チャージ',
//         send: '送信',
//         openChat: 'チャットを開く',
//         closeChat: 'チャットを閉じる',
//         placeHolder: 'メッセージを入力してください...',
//         generating: "回答を生成中...",
//         headerText: ['こんにちは。', 'Goya Chat AIへようこそ。', 'Goya Chat AIは有料サービスです。', 'Chat AIの質問をクリックしてください。'],
//         askQuestion: 'Chat AIに質問する',
//         initialQuery: ['エントリーに適した暗号通貨のおすすめ', '暗号通貨市場の動向', 'Goyaスコアとは？'],
//         otherNews: '他の暗号通貨ニュース',
//         analyzeBtc: 'ビットコインのスコアと価格分析',
//         recommendCrypto: 'エントリーに適した暗号通貨のおすすめ',
//         marketTrend: '暗号通貨市場の見通し',
//         majorNews: '暗号通貨関連の主要ニュース',
//         aboutGoya: 'Goyaスコアとは？',
//         explainSymbol: 'について教えてください',
//         analyzeSymbol: ' のスコアと価格分析',
//         analyzeSymbolMonth: ' 過去1ヶ月のスコアと価格分析',
//         otherCrypto: '他の暗号通貨のおすすめ',
//         explainCriteria: 'おすすめの基準について教えてください'
//     },
//     en: {
//         connectedTime: 'Connected Time',
//         charge: 'Charge',
//         send: 'Send',
//         openChat: 'Open Chat',
//         closeChat: 'Close Chat',
//         placeHolder: 'Please enter a message...',
//         generating: "Generating response...",
//         headerText: ['Hello.', 'Welcome to Goya Chat AI.', 'Goya Chat AI is a paid service.', 'Please click on a Chat AI question.'],
//         askQuestion: 'Ask Chat AI',
//         initialQuery: ['Recommended cryptocurrencies to enter', 'Cryptocurrency market trends', 'What is the Goya Score?'],
//         otherNews: 'Other cryptocurrency news',
//         analyzeBtc: 'Bitcoin score and price analysis',
//         recommendCrypto: 'Recommended cryptocurrencies to enter',
//         marketTrend: 'Cryptocurrency market outlook',
//         majorNews: 'Major news about cryptocurrencies',
//         aboutGoya: 'What is the Goya Score?',
//         explainSymbol: 'Tell me about',
//         analyzeSymbol: ' score and price analysis',
//         analyzeSymbolMonth: ' one-month score and price analysis',
//         otherCrypto: 'Other recommended cryptocurrencies',
//         explainCriteria: 'Tell me about the recommendation criteria'
//     }
// };
//
// const timeUnits = {
//
// };
//
// let selectedLanguage = 'kr'; // Default selected language
// function handleLanguageChange(event) {
//     selectedLanguage = event.target.value;
//     console.log("Selected Language:", selectedLanguage);
//     initText();
//     changeText();
// }
//
// // Attach the event listener to the select element
// document.getElementById('language-select').addEventListener('change', handleLanguageChange);
//
// function initText() {
//     console.log("changeText()");
//     const langText = texts[selectedLanguage];
//
//     // Get all elements that need to be changed
//     const chargeText = document.getElementById('add-button');
//     const connectTimeText = document.getElementById('connect-time');
//     const headerTextSpans = document.querySelectorAll('#header-text span');
//     const askQuestionText = document.getElementById('ask-question');
//     const initialQueryElems = document.querySelectorAll('#initial-query p');
//     const placeHolderText = document.getElementById('message-input');
//     const openChatText = document.getElementById('input-open');
//     const closeChatText = document.getElementById('input-close');
//
//     // Update the text content of the elements
//     chargeText.textContent = langText.charge;
//     connectTimeText.textContent = langText.connectedTime;
//     headerTextSpans.forEach((span, index) => {
//         console.log('index: ', index);
//         if (langText.headerText[index]) {
//             span.textContent = langText.headerText[index];
//         }
//     });
//     askQuestionText.textContent = langText.askQuestion;
//     initialQueryElems.forEach((p, index) => {
//         console.log('index: ', index);
//         if (langText.initialQuery[index]) {
//             p.textContent = langText.initialQuery[index];
//         }
//     });
//     placeHolderText.placeholder = langText.placeHolder;
//     openChatText.textContent = langText.openChat;
//     closeChatText.textContent = langText.closeChat;
//
// }
//
// // Function to map class names to keys in the texts object
// function getClassToKeyMap() {
//     return {
//         'recommend-crypto': 'recommendCrypto',
//         'market-trend': 'marketTrend',
//         'major-news': 'majorNews',
//         'about-goya': 'aboutGoya',
//         'other-news': 'otherNews',
//         'analyze-btc': 'analyzeBtc',
//         'explain-symbol': 'explainSymbol',
//         'analyze-symbol': 'analyzeSymbol',
//         'analyze-symbol-month': 'analyzeSymbolMonth',
//         'other-crypto': 'otherCrypto',
//         'explain-criteria': 'explainCriteria'
//     };
// }
//
// // New function to change text for all elements with specific classes
// function changeText() {
//     const langText = texts[selectedLanguage];
//     const classToKeyMap = getClassToKeyMap();
//
//     // Loop over each key in the map and update elements with the corresponding class
//     for (const className in classToKeyMap) {
//         const key = classToKeyMap[className];
//         const elements = document.querySelectorAll(`.${className}`);
//
//         elements.forEach(element => {
//             if (langText[key]) {
//                 // Handle special cases for keys that require symbol name prepending
//                 if (key === 'explainSymbol' || key === 'analyzeSymbol' || key === 'analyzeSymbolMonth') {
//                     // Extract the symbol name from the current textContent of the element
//                     const symbolMatch = element.textContent.match(/^[^\s]+/); // Assumes the symbol is the first word
//                     const symbol = symbolMatch ? symbolMatch[0] : '';
//
//                     // Prepend the symbol to the translation text
//                     element.textContent = `${symbol}${langText[key]}`;
//                 } else {
//                     // Default case: set the text directly
//                     element.textContent = langText[key];
//                 }
//             }
//         });
//     }
// }
//
//
//
