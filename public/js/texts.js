const texts = {
    kr: {
        connectedTime: '접속 시간',
        charge: '충전',
        send: '전송',
        openChat: '&#9650; 채팅창 열기',
        closeChat: '&#9660; 채팅창 닫기',
        placeHolder: '메시지를 입력해 주세요...',
        generating: "답변 생성중...",
        headerText: ['안녕하세요.', 'Goya Chat AI에 오신 것을 환영합니다.', 'Goya Chat AI는 사용료가 부과되는 유료 서비스입니다.', 'Chat AI 질문을 클릭해 주세요.'],
        sirText: ['님 안녕하세요.'],
        askQuestion: 'Chat AI 질문하기',
        initialQuery: ['진입하기 좋은 암호 화폐 추천', '암호 화폐 시장 전망', '고야 스코어란?'],
        timezone: 'Asia/Seoul',
        recommendCrypto: '진입하기 좋은 암호화폐 추천',
        marketTrend: '암호 화폐 시장 전망',
        majorNews: '주요 암호 화폐 뉴스',
        aboutGoya: '고야 스코어란?',
        otherNews: '다른 주요 뉴스',
        analyzeBtc: '비트코인 스코어 및 가격',
        explainSymbolHead: '암호화폐 ',
        explainSymbol: '에 대해 알려줘',
        analyzeSymbol: ' 24시간 스코어　정보',
        analyzeSymbolMonth: ' 한 달간 스코어 정보',
        otherCrypto: '다른 암호 화폐 추천',
        explainCriteria: '추천 기준을 알려줘',
        caution: '※ 본 암호화폐 추천은 투자시 절대적인 기준이 아닌 참고 용도로만 사용하시기 바랍니다. 투자하시기 전 반드시 마지막 시그널 발생 시간을 확인하시고 실제 차트와 비교하신 후에 진입하셔야 합니다. 또한 투자로 발생한 모든 손실 및 책임은 투자자 본인에게 있는 점을 다시 한 번 알려드립니다.',
        errorMessage: '응답 생성에 실패하였습니다.',
        connectTime: '접속 시간',
        recommendOpenBtn: '시그널 보기',
        recommendCloseBtn: '닫기'
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
        sirText: ['様、こんにちは。'],
        askQuestion: 'Chat AIに質問',
        initialQuery: ['エントリーに適した暗号通貨のおすすめ', '暗号通貨市場の展望', 'Goyaスコアとは？'],
        timezone: 'Asia/Tokyo',
        recommendCrypto: 'エントリーに適した暗号通貨のおすすめ',
        marketTrend: '暗号通貨市場の展望',
        majorNews: '主要な暗号通貨ニュース',
        aboutGoya: 'Goyaスコアとは？',
        otherNews: '他の主要なニュース',
        analyzeBtc: 'ビットコインのスコアと価格',
        explainSymbolHead: '暗号通貨',
        explainSymbol: 'について説明してください',
        analyzeSymbol: 'の過去24時間のスコアデータ',
        analyzeSymbolMonth: 'の過去1ヶ月のスコアデータ',
        otherCrypto: '他の暗号通貨のおすすめ',
        explainCriteria: '推奨基準について説明してください',
        caution: '※ 本暗号通貨の推薦は、投資の絶対的な基準ではなく、参考目的でのみご利用ください。投資を行う前に、必ず最後のシグナル発生時刻を確認し、実際のチャートと比較した上でエントリーしてください。また、投資によって生じたすべての損失および責任は、投資家ご自身にあることを改めてご確認ください。',
        errorMessage: '回答生成に失敗しました。',
        connectTime: '接続時間',
        recommendOpenBtn: 'シグナルを確認',
        recommendCloseBtn: '閉じる'
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
        sirText: ['Hello, '],
        askQuestion: 'Ask Chat AI',
        initialQuery: ['Recommended cryptocurrencies to enter', 'Cryptocurrency market outlook', 'What is the Goya Score?'],
        timezone: 'UTC',
        recommendCrypto: 'Recommended cryptocurrencies to enter',
        marketTrend: 'Cryptocurrency market outlook',
        majorNews: 'Major cryptocurrency news',
        aboutGoya: 'What is the Goya Score?',
        otherNews: 'Other major news',
        analyzeBtc: 'Bitcoin score and price',
        explainSymbol: 'Tell me about the cryptocurrency ',
        analyzeSymbol: ' 24 hrs score data',
        analyzeSymbolMonth: ' one-month score data',
        otherCrypto: 'Other cryptocurrency recommendations',
        explainCriteria: 'Tell me about the recommendation criteria',
        caution: '※ This cryptocurrency recommendation should be used for reference purposes only and not as an absolute standard for investment. Before investing, be sure to check the time of the last signal and compare it with the actual chart before entering. Please note once again that all losses and liabilities arising from investments rest solely with the investor.',
        errorMessage: 'Failed to generate message.',
        connectTime: 'Connected time',
        recommendOpenBtn: 'View Signal',
        recommendCloseBtn: 'Close'
    },
    zh: {  // Example for Chinese
        connectedTime: '连接时间',
        charge: '充电',
        send: '发送',
        openChat: '打开聊天',
        closeChat: '关闭聊天',
        placeHolder: '请输入消息...',
        generating: "生成回复中...",
        headerText: ['你好。', '欢迎来到Goya Chat AI。', 'Goya Chat AI是一个付费服务。', '请点击Chat AI的问题。'],
        askQuestion: '询问Chat AI',
        initialQuery: ['推荐进入的加密货币', '加密货币市场趋势', '什么是Goya评分？'],
        timezone: 'Asia/Shanghai',
        recommendCrypto: '推荐进入的加密货币',
        marketTrend: '加密货币市场趋势',
        majorNews: '主要加密货币新闻',
        aboutGoya: '什么是Goya评分？',
        otherNews: '其他主要新闻',
        analyzeBtc: '比特币评分和价格',
        explainSymbol: '是什么?',
        analyzeSymbol: ' 24小时得分数据',
        analyzeSymbolMonth: ' 一个月得分数据',
        otherCrypto: '其他加密货币推荐',
        explainCriteria: '告诉我推荐标准',
        caution: '本加密货币推荐仅供参考，不能作为投资的绝对标准。在投资之前，请务必确认最后的信号发生时间，并与实际图表进行比较后再进行操作。此外，请再次确认，所有因投资产生的损失和责任均由投资者本人承担。',
        errorMessage: '无法生成消息',
        connectTime: '连接时间',
        recommendOpenBtn: '查看信号',
        recommendCloseBtn: '关闭'
    }
};
const timeUnits = {
    kr: {
        hour: '시간 ',
        hours: '시간 ',
        minute: '분 ',
        minutes: '분 ',
        articles: '전 (작성 시간)',
        analysis: '전 (마지막 스코어 기록 시간)',
        recommend: '전 (마지막 시그널 발생 시간)'
    },
    jp: {
        hour: '時間',
        hours: '時間',
        minute: '分',
        minutes: '分',
        articles: '前（作成時間）',
        analysis: '前（最新スコアの記録時間）',
        recommend: '前（最新シグナルの記録時間）'
    },
    en: {
        hour: ' hour ',
        hours: ' hours ',
        minute: ' minute ',
        minutes: ' minutes ',
        articles: 'ago (Time Written)',
        analysis: 'ago (Last Score/Price Time)',
        recommend: 'ago (Last Signal Time)'
    }
};

const timeZoneAbbreviations = {
    'Asia/Seoul': 'KST',
    'Asia/Tokyo': 'JST',
    'UTC': 'UTC'
};

let selectedLanguage = 'kr'; // Default selected language
let username = '';
let currentEpoch = Date.now();

let langDivs = document.querySelectorAll('.select-lang');
langDivs.forEach(div => {
    div.addEventListener('click', function() {
        if (this.classList.contains('selected')) {
            return; // Exit the function if it already has the 'selected' class
        }
        langDivs.forEach(d => d.classList.remove('selected'));
        this.classList.add('selected');
        selectedLanguage = this.getAttribute('data-lang');
        console.log('Selected language: ', selectedLanguage);
        let languageDiv = document.createElement('div');
        languageDiv.classList.add('message', 'left');
        let assistantDiv = document.createElement('div');
        assistantDiv.classList.add('assistant');
        // Set textContent based on selected language
        if (selectedLanguage === 'kr') {
            assistantDiv.textContent = '기본 언어로 한국어를 선택하였습니다.';
        } else if (selectedLanguage === 'jp') {
            assistantDiv.textContent = 'デフォルト言語として日本語が選ばれました。';
        } else if (selectedLanguage === 'en') {
            assistantDiv.textContent = 'English has been selected as the default language.';
        }
        languageDiv.appendChild(assistantDiv);
        const chatBox = document.querySelector('#chat-box');
        chatBox.appendChild(languageDiv);
        // Scroll to the bottom of the chat box
        chatBox.scrollTop = chatBox.scrollHeight;

        handleLanguageChange();

    });
})

function handleLanguageChange(event) {
    console.log("Selected Language:", selectedLanguage);
    initText();
    changeText();
}

// Attach the event listener to the select element
// document.getElementById('language-select').addEventListener('change', handleLanguageChange);

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

// function initText() {
//     console.log("Username: ", username);
//     const langText = texts[selectedLanguage];
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
//     // connectTimeText.textContent = langText.connectedTime;
//     // headerTextSpans.forEach((span, index) => {
//     //     if (langText.headerText[index]) {
//     //         span.textContent = langText.headerText[index];
//     //     }
//     // });
//
//     headerTextSpans.forEach((span, index) => {
//         if (index === 0 && username && username.trim() !== '') {
//             // First headerTextSpan: add sirText + username
//             span.textContent = langText.sirText + " " + username;
//             // If selected language is 'en', append a dot
//             if (selectedLanguage === 'en') {
//                 span.textContent += ".";
//             }
//         } else if (langText.headerText[index]) {
//             span.textContent = langText.headerText[index];
//         }
//     });
//
//     askQuestionText.textContent = langText.askQuestion;
//     initialQueryElems.forEach((p, index) => {
//         if (langText.initialQuery[index]) {
//             p.textContent = langText.initialQuery[index];
//         }
//     });
//     placeHolderText.placeholder = langText.placeHolder;
//     openChatText.innerHTML = langText.openChat;
//     closeChatText.innerHTML = langText.closeChat;
//
//     // Set login time based on selected language
//     // setLoginTime();
// }
async function initText() {
    console.log("Username: ", username);
    const langText = texts[selectedLanguage];
    // Get all elements that need to be changed
    const chargeText = document.getElementById('add-button');
    const sendText = document.getElementById('send-button');
    const connectTimeText = document.getElementById('connect-time');
    const headerTextSpans = document.querySelectorAll('#header-text span');
    const askQuestionText = document.getElementById('ask-question');
    const initialQueryElems = document.querySelectorAll('#initial-query p');
    const placeHolderText = document.getElementById('message-input');
    const openChatText = document.getElementById('input-open');
    const closeChatText = document.getElementById('input-close');

    // Update the text content of the elements
    chargeText.textContent = langText.charge;
    sendText.textContent = langText.send;
    // connectTimeText.textContent = langText.connectedTime;
    headerTextSpans.forEach((span, index) => {
        if (index === 0 && username && username.trim() !== '') {
            // First headerTextSpan logic based on language
            if (selectedLanguage === 'en') {
                // If 'en', append username after sirText
                span.textContent = langText.sirText + username;
                // Add a dot if language is 'en'
                span.textContent += ".";
            } else {
                // If not 'en', prepend username to sirText
                span.textContent = username + langText.sirText;
            }
        } else if (langText.headerText[index]) {
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
    openChatText.innerHTML = langText.openChat;
    closeChatText.innerHTML = langText.closeChat;

    // Set login time based on selected language
    await setConnectTime(currentEpoch);
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
        'explain-criteria': 'explainCriteria',
        'error-message': 'errorMessage',
        'connect-time': 'connectTime',
        'recommend-open-btn': 'recommendOpenBtn',
        'recommend-close-btn': 'recommendCloseBtn'
    };
}

// New function to change text for all elements with specific classes
async function changeText() {
    const langText = texts[selectedLanguage];
    const classToKeyMap = getClassToKeyMap();

    // Loop over each key in the map and update elements with the corresponding class
    for (const className in classToKeyMap) {
        const key = classToKeyMap[className];
        // console.log("key: ", key);
        const elements = document.querySelectorAll(`.${className}`);
        elements.forEach(element => {
            if (langText[key]) {
                // Handle special cases for keys that require symbol name prepending
                if (key === 'explainSymbol' || key === 'analyzeSymbol' || key === 'analyzeSymbolMonth') {
                    // const symbolMatch = element.textContent.match(/^[^\s]+/); // Assumes the symbol is the first word
                    // const symbol = symbolMatch ? symbolMatch[0] : '';
                    // element.textContent = `${symbol}${langText[key]}`;
                    console.log('element: ', element);
                    const symbol = element.getAttribute('data-symbol');
                    if (symbol) {
                        // If the key is explainSymbol and the selected language is 'en', append the symbol
                        if (key === 'explainSymbol' && selectedLanguage === 'en') {
                            element.textContent = `${langText[key]} ${symbol}`; // Append symbol
                        } else {
                            // Prepend the symbol in other cases
                            element.textContent = `${symbol}${langText[key]}`;
                        }
                    }
                } else {
                    element.textContent = langText[key];
                }
            }
        });
        await setConnectTime(currentEpoch);
    }
    // Set login time based on selected language
    // setLoginTime();
}
async function getUsername(){
    try {
        const response = await fetch('/user/1');
        if (response.ok) {
            const userInfo = await response.json();
            console.log("UserInfo: ", userInfo);
            username = userInfo.name;
        }
    } catch (error) {
        console.error('Error: ', error);
    }
}


async function setConnectTime(currentEpoch) {
    const date = new Date(currentEpoch);

    // Define the options common to all languages, using 'en-US' locale
    const options = {
        timeZone: 'Asia/Seoul',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: false // Use 24-hour format
    };

    // Format the time using 'en-US'
    const formattedTime = new Intl.DateTimeFormat('en-US', options).format(date);

    // Modify based on the selected language
    let finalFormattedTime = formattedTime;

    if (selectedLanguage === 'kr') {
        // For Korean: add Korean units
        finalFormattedTime = formattedTime
            .replace('/', '월 ')  // Replace '/' with '월'
            .replace(',', '일')   // Replace ',' with '일'
            .replace(':', '시 ') + '분';  // Add '시' after the hour and '분' after the minute
    } else if (selectedLanguage === 'jp') {
        // For Japanese: add Japanese units
        finalFormattedTime = formattedTime
            .replace('/', '月')  // Replace '/' with '月'
            .replace(',', '日')   // Replace ',' with '日'
            .replace(':', '時') + '分';  // Add '時' after the hour and '分' after the minute
    }
    // If 'en', no changes are made to the formattedTime

    console.log("Time in Seoul: " + finalFormattedTime);
    document.getElementById('login-time').textContent = finalFormattedTime;
}




// Call initText initially to set up the text based on the default selected language
document.addEventListener('DOMContentLoaded', async () => {
    await getUsername();
    initText();
});
