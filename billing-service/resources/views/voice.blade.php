<style>
  .glass {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
  }
</style>
<meta name="csrf-token" content="{{ session('csrf_token') ?? '' }}">

<div class="relative w-full overflow-hidden bg-slate-50 font-sans rounded-3xl shadow-sm border border-slate-200">

  <div class="absolute -top-20 -left-20 w-64 h-64 sm:w-96 sm:h-96 bg-indigo-200/40 blur-[80px] rounded-full pointer-events-none"></div>
  <div class="absolute -bottom-20 -right-20 w-64 h-64 sm:w-96 sm:h-96 bg-purple-200/40 blur-[80px] rounded-full pointer-events-none"></div>
 <main class="relative z-10 w-full max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-6 sm:space-y-10">
    <header class="text-center space-y-4">
      <div class="flex flex-col sm:flex-row items-center justify-between w-full gap-4">
        
        <div class="w-full sm:w-40 hidden sm:block"></div> 

        <span class="inline-block px-4 py-1.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase shadow-sm">
          AI Speaking Analyzer
        </span>

        <div class="flex items-center justify-center sm:justify-end gap-0.5 w-full sm:w-40">
          <div class="flex items-center gap-2 bg-amber-50 text-amber-700 px-4 py-2 rounded-l-2xl border border-amber-100 border-r-0 shadow-sm" title="Available Credits">
            <i data-lucide="zap" class="w-4 h-4 fill-amber-400 text-amber-400"></i>
            <span class="text-xs font-black uppercase tracking-tighter whitespace-nowrap">
             <span id="creditCountSpeaking">{{ $userCredits ?? 3 }}</span> Credits
          </div>
          <button onclick="showPage('buycredits-page')" class="flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white w-10 h-10 sm:w-9 sm:h-9 rounded-r-2xl border border-amber-500 shadow-sm transition-all hover:scale-105 active:scale-95 group shrink-0" title="Buy Credits">
            <i class="fas fa-plus w-4 h-4 sm:w-5 sm:h-5 transition-transform group-hover:rotate-90"></i>
          </button>
        </div>
      </div>
      
      <div class="pt-2 px-2 sm:px-0">
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Practice <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Speaking</span>
        </h1>
        <p class="text-sm sm:text-base text-slate-500 max-w-xl mx-auto mt-3 sm:mt-4">
          Read aloud, record your voice, and receive detailed speaking feedback.
        </p>
      </div>
    </header>

    <div class="flex flex-col items-center justify-center gap-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-md sm:max-w-lg">
        
        <button id="basicMode" class="relative p-4 rounded-xl bg-white border-2 border-indigo-600 text-left shadow transition active:scale-95 w-full">
          <div class="absolute top-3 right-3 flex items-center gap-1 bg-amber-50 text-amber-700 px-2 py-0.5 rounded-md border border-amber-100">
            <i class="fas fa-coins text-[8px]"></i>
            <span class="text-[9px] font-black uppercase">2 Credits</span>
          </div>
          <div class="text-xl sm:text-lg">⚡</div>
          <div class="font-bold text-sm sm:text-base mt-1 sm:mt-0">Basic</div>
          <p class="text-xs sm:text-[10px] text-slate-500 mt-1">Fast & lightweight analysis</p>
        </button>

        <button id="deepMode" 
    @class([
        'relative p-4 rounded-xl border-2 transition-all text-left overflow-hidden group active:scale-95 w-full',
        'bg-slate-100/50 border-slate-200 cursor-pointer' => $userPlan === 'free',
        'bg-white border-slate-200 hover:border-indigo-400 hover:shadow-md' => $userPlan !== 'free'
    ])
>
    @if($userPlan === 'free')
        <div class="absolute top-3 right-3 flex items-center gap-1.5 bg-slate-200 text-slate-500 px-2 py-1 rounded-md">
            <i class="fas fa-lock w-3 h-3 text-[10px]"></i>
            <span class="text-[10px] font-bold uppercase">Pro</span>
        </div>
    @else
        <div class="absolute top-3 right-3 flex items-center gap-1 bg-amber-50 text-amber-700 px-2 py-0.5 rounded-md border border-amber-100">
            <i class="fas fa-coins text-[8px]"></i>
            <span class="text-[9px] font-black uppercase">3 Credits</span>
        </div>
    @endif

    <div class="flex flex-col gap-1">
        <div @class(['text-xl sm:text-2xl', 'grayscale opacity-50' => $userPlan === 'free'])>🧠</div>
        
        <div @class([
            'font-bold text-sm sm:text-base', 
            'text-slate-500' => $userPlan === 'free', 
            'text-slate-900' => $userPlan !== 'free'
        ])>
            Deep Analysis
        </div>

        <p @class([
            'text-xs sm:text-[10px] leading-relaxed',
            'text-slate-400 italic' => $userPlan === 'free',
            'text-slate-500' => $userPlan !== 'free'
        ])>
            @if($userPlan === 'free')
                Unlock detailed feedback and scoring...
            @else
                Comprehensive report on grammar, fluency, and logic.
            @endif
        </p>
    </div>

    @if($userPlan !== 'free')
        <div class="absolute bottom-0 left-0 h-1 w-0 bg-indigo-500 transition-all group-hover:w-full"></div>
    @endif
</button>
      </div>
      <span id="creditCostDisplay" class="hidden text-sm text-slate-500"></span>
    </div>

    <section class="glass rounded-[2rem] p-4 sm:p-10 shadow-xl shadow-indigo-200/30 space-y-6 sm:space-y-10 mx-1">

      <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400">Practice Sentence</span>
          <button id="changeSentence" class="text-xs font-bold text-indigo-600 hover:text-purple-600 transition-colors">Change <i class="fas fa-sync-alt ml-1"></i></button>
        </div>
        <p id="sentence" class="text-lg sm:text-xl md:text-2xl text-slate-700 font-medium leading-relaxed">
          The quick brown fox jumps over the lazy dog near the river bank.
        </p>
      </div>

      <div class="flex flex-col items-center gap-6"> 
        
        <div id="visualizer" class="hidden flex items-end gap-1 sm:gap-1.5 h-12 sm:h-16">
          <div class="bar w-2 bg-indigo-500 rounded-t-sm"></div><div class="bar w-2 bg-indigo-500 rounded-t-sm"></div>
          <div class="bar w-2 bg-indigo-500 rounded-t-sm"></div><div class="bar w-2 bg-indigo-500 rounded-t-sm"></div>
          <div class="bar w-2 bg-indigo-500 rounded-t-sm"></div><div class="bar w-2 bg-indigo-500 rounded-t-sm"></div>
          <div class="bar w-2 bg-indigo-500 rounded-t-sm"></div><div class="bar w-2 bg-indigo-500 rounded-t-sm"></div>
        </div>

        <button id="recordBtnn" class="w-full sm:w-auto px-8 sm:px-14 py-4 sm:py-5 rounded-full bg-slate-900 hover:bg-indigo-600 text-white font-extrabold text-lg sm:text-xl transition-all active:scale-95 shadow-lg sm:shadow-xl shadow-slate-300">
          <span id="btnTextRecord" class="flex items-center justify-center gap-2">🎙️ Start Recording</span>
        </button>
        
        <p class="text-xs sm:text-sm text-slate-400">Click to start voice analysis</p>
      </div>
    </section>

    <section id="results" class="hidden space-y-6 px-1">

      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Accuracy</h3>
          <p class="text-xs sm:text-sm text-slate-500 mb-2">Meaning + Pronunciation</p>
          <p id="accuracy" class="text-2xl sm:text-3xl font-extrabold text-indigo-700">0%</p>
        </div>

        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Meaning Match</h3>
          <p class="text-xs sm:text-sm text-slate-500 mb-2">Word overlap</p>
          <p id="semantic" class="text-2xl sm:text-3xl font-extrabold text-purple-700">0%</p>
        </div>

        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Text Accuracy</h3>
          <p class="text-xs sm:text-sm text-slate-500 mb-2 opacity-0 hidden sm:block">Overall metric</p>
          <p id="textAcc" class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-auto">0%</p>
        </div>

        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Grammar</h3>
          <p id="grammar" class="text-2xl sm:text-3xl font-extrabold text-indigo-700 mt-2 sm:mt-6">0%</p>
        </div>

        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Fluency</h3>
          <p id="fluency" class="text-2xl sm:text-3xl font-extrabold text-purple-700 mt-2 sm:mt-6">0%</p>
        </div>

        <div class="bg-white/60 backdrop-blur p-4 sm:p-6 rounded-2xl border border-white/50 shadow-sm">
          <h3 class="font-bold text-slate-800 mb-1 sm:mb-2 text-sm sm:text-base">Pronunciation</h3>
          <p id="pronunciation" class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-2 sm:mt-6">0%</p>
        </div>
      </div>

      <div id="waveform-container" class="bg-white/80 backdrop-blur p-4 sm:p-6 rounded-2xl w-full border border-white/50 shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <h3 class="font-bold text-slate-800">Voice Analysis Visualizer</h3>
          <button id="playBtn" class="w-full sm:w-auto px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition flex items-center justify-center gap-2 font-medium shadow-sm active:scale-95">
            <i class="fas fa-play text-sm"></i> Play Recording
          </button>
        </div>
        <div id="waveform" class="bg-slate-50/50 rounded-lg w-full overflow-hidden"></div>
      </div>

      <div class="flex flex-col md:flex-row items-stretch gap-4 sm:gap-6">
        
        <div class="bg-white/60 backdrop-blur p-6 rounded-2xl w-full md:w-1/3 lg:w-1/4 text-center border border-white/50 shadow-sm flex flex-col justify-center">
          <h3 class="font-bold text-slate-800 mb-2">Hesitations / Pauses</h3>
          <p id="hesitation" class="text-2xl sm:text-3xl font-bold text-slate-700">0%</p>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">Fewer pauses = higher fluency</p>
        </div>

        <div class="flex flex-col items-center justify-center w-full md:w-2/3 lg:w-3/4">
          <button id="aiTipsBtn" class="hidden px-6 py-4 sm:py-5 rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-bold transition-all w-full shadow-md active:scale-95 text-sm sm:text-base">
            🤖 Get Advanced AI Pronunciation Feedback
          </button>
        </div>
      </div>

      <div id="aiTips" class="hidden w-full mt-2"></div>

      <div class="flex justify-center pt-8 pb-4">
        <button id="resetAllBtn" class="w-full sm:w-auto px-8 py-3.5 rounded-xl border-2 border-slate-200 text-slate-500 font-bold hover:bg-slate-100 hover:text-slate-700 transition-all flex items-center justify-center gap-2 active:scale-95">
          <i class="fas fa-undo"></i> Reset and Try Again
        </button>
      </div>

    </section>
  </main>
</div>

<script>
  /* =========================================================
     1. CONFIGURATION & DOM ELEMENTS
  ========================================================= */
  let analysisMode = "basic";
  let recording = false;
  let mediaRecorder, stream, audioChunks = [];
  let audioContext, analyser, raf;

  const recordBtnn = document.getElementById("recordBtnn");
  const btnText = document.getElementById("btnTextRecord");
  const visualizer = document.getElementById("visualizer");
  const bars = document.querySelectorAll(".bar");
  const results = document.getElementById("results");
  const sentenceEl = document.getElementById("sentence");
  const basicBtn = document.getElementById("basicMode");
  const deepBtn = document.getElementById("deepMode");
  const aiBtn = document.getElementById("aiTipsBtn"); 
  const aiTipsBox = document.getElementById("aiTips"); 
  let aiFeedbackCache = {};

  // Sentence Pool
  const sentences = [
    "The quick brown fox jumps over the lazy dog near the river bank.",
    "Through the thick thorns, they thought they saw three thousand things.",
    "Strictly speaking, the strengths of the squirrels were underestimated.",
    "She asked for baked bread but received cooked and salted meat instead.",
    "The university's administrative laboratory is technologically revolutionary.",
    "Particularly spectacular phenomena are often photographed by archaeologists.",
    "The sixth sick sheik's sixth sheep's sick.",
    "Maintaining a healthy routine requires discipline and consistency.",
    "Artificial intelligence is transforming education and healthcare.",
    "The bridges across the villages were managed by various agencies."
  ];

  document.getElementById("changeSentence").onclick = () => {
      sentenceEl.textContent = sentences[Math.floor(Math.random() * sentences.length)];
  };

  const USER_PLAN = <?php echo json_encode($userPlan); ?>; 

  function setMode(mode) {
      if (mode === 'deep' && USER_PLAN === 'free') {
          showUpgradePopup();
          return; 
      }

      analysisMode = mode;
      const creditDisplay = document.getElementById("creditCostDisplay");
      if (mode === "deep") {
          creditDisplay.innerHTML = `<i class="fas fa-coins mr-1"></i> 3 Credits per Analysis (Deep Mode Active)`;
      } else {
          creditDisplay.innerHTML = `<i class="fas fa-coins mr-1"></i> 2 Credit per Analysis (Basic Mode Active)`;
      }
      creditDisplay.classList.remove("hidden");

      const activeClass = "relative p-4 rounded-xl bg-white border-2 border-indigo-600 text-left shadow transition w-full";
      const inactiveClass = "relative p-4 rounded-xl bg-slate-50 border-2 border-slate-200 text-left transition hover:border-indigo-400 w-full";
      
      basicBtn.className = mode === "basic" ? activeClass : inactiveClass;
      deepBtn.className = mode === "deep" ? activeClass : inactiveClass;
  }

  function showUpgradePopup() {
      Swal.fire({
          title: '<span class="text-indigo-900 font-bold">Unlock Deep Analysis</span>',
          html: `
              <div class="text-left space-y-3">
                  <p class="text-slate-600">Our <b>Deep Mode</b> uses advanced AI to analyze:</p>
                  <ul class="text-sm text-slate-500 list-disc ml-5">
                      <li>Granular grammatical errors</li>
                      <li>Sentence logic and flow</li>
                      <li>Advanced vocabulary suggestions</li>
                  </ul>
                  <p class="text-indigo-600 font-semibold mt-4">Upgrade to Pro to access this feature!</p>
              </div>
          `,
          icon: 'info',
          iconColor: '#6366f1',
          showCancelButton: true,
          confirmButtonText: '🚀 Upgrade Now',
          cancelButtonText: 'Maybe Later',
          confirmButtonColor: '#4f46e5',
          cancelButtonColor: '#94a3b8',
          reverseButtons: true,
          customClass: {
              popup: 'rounded-3xl border-none shadow-2xl p-4 sm:p-6 w-[90%] sm:w-auto',
              confirmButton: 'rounded-xl px-6 py-3 font-bold w-full sm:w-auto mb-2 sm:mb-0',
              cancelButton: 'rounded-xl px-6 py-3 font-medium w-full sm:w-auto'
          }
      }).then((result) => {
          if (result.isConfirmed) {
              window.location.href = 'pricing.php'; 
          }
      });
  }

  basicBtn.onclick = () => setMode("basic");
  deepBtn.onclick = () => setMode("deep");

  /* =========================================================
     2. WAVESURFER (AUDIO PLAYER)
  ========================================================= */
  const regionsPlugin = WaveSurfer.Regions.create();
  const wave = WaveSurfer.create({
      container: "#waveform",
      height: window.innerWidth < 640 ? 80 : 120, // Responsive height for wave
      waveColor: "#cbd5e1",
      progressColor: "#6366f1",
      cursorColor: "#4338ca",
      barWidth: 3,
      barRadius: 3,
      barGap: 2,
      plugins: [regionsPlugin]
  });

  // Handle window resize for wavesurfer
  window.addEventListener('resize', () => {
    wave.setOptions({ height: window.innerWidth < 640 ? 80 : 120 });
  });

  const playBtn = document.getElementById("playBtn");
  playBtn.onclick = () => wave.playPause();

  wave.on('play', () => { playBtn.innerHTML = '<i class="fas fa-pause text-sm"></i> Pause'; });
  wave.on('pause', () => { playBtn.innerHTML = '<i class="fas fa-play text-sm"></i> Play Recording'; });
  wave.on('finish', () => { playBtn.innerHTML = '<i class="fas fa-play text-sm"></i> Play Recording'; });

  /* =========================================================
     3. RECORDING LOGIC
  ========================================================= */
  async function startRecording() {
      try {
          stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          mediaRecorder = new MediaRecorder(stream);
          audioChunks = [];

          mediaRecorder.ondataavailable = (e) => {
              if (e.data.size > 0) audioChunks.push(e.data);
          };

          mediaRecorder.start();
          visualizer.classList.remove("hidden");
          setupLiveVisualizer(stream);
          
      } catch (err) {
          console.error("Microphone access denied:", err);
          Swal.fire('Microphone Error', 'Please allow microphone access to use this feature.', 'error');
          resetUI();
      }
  }

  function stopRecording() {
      return new Promise((resolve) => {
          if (!mediaRecorder) { resolve(null); return; }
          
          mediaRecorder.onstop = () => {
              stream.getTracks().forEach(t => t.stop());
              cancelAnimationFrame(raf);
              visualizer.classList.add("hidden");
              const blob = new Blob(audioChunks, { type: "audio/webm" });
              resolve(blob);
          };
          mediaRecorder.stop();
      });
  }

  function setupLiveVisualizer(stream) {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
      analyser = audioContext.createAnalyser();
      analyser.fftSize = 64;
      const source = audioContext.createMediaStreamSource(stream);
      source.connect(analyser);
      const dataArray = new Uint8Array(analyser.frequencyBinCount);

      const animate = () => {
          analyser.getByteFrequencyData(dataArray);
          bars.forEach((bar, i) => {
              bar.style.height = Math.max(8, dataArray[i] / 2) + "px";
          });
          raf = requestAnimationFrame(animate);
      };
      animate();
  }

  function resetUI() {
      recording = false;
      recordBtnn.disabled = false;
      recordBtnn.className = "w-full sm:w-auto px-8 sm:px-14 py-4 sm:py-5 rounded-full bg-slate-900 hover:bg-indigo-600 text-white font-extrabold text-lg sm:text-xl transition-all active:scale-95 shadow-lg sm:shadow-xl shadow-slate-300";
      btnText.innerHTML = '<i class="fas fa-microphone"></i> Start Recording';
  }

  /* =========================================================
     4. BACKEND & ANALYSIS LOGIC
  ========================================================= */
  let recordingTimer; 
  const MAX_RECORDING_SECONDS = 60; 
  let secondsElapsed = 0;

  recordBtnn.onclick = async () => {
      if (!recording) {
          recording = true;
          secondsElapsed = 0;
          btnText.innerHTML = `<i class="fas fa-stop"></i> Stop Recording (0:${MAX_RECORDING_SECONDS})`;
          recordBtnn.classList.replace("bg-slate-900", "bg-red-600");
          recordBtnn.classList.replace("hover:bg-indigo-600", "hover:bg-red-700");
          
          results.classList.add("hidden");
          document.getElementById("aiTips").classList.add("hidden");
          document.getElementById("aiTipsBtn").classList.add("hidden");
          
          await startRecording();

          recordingTimer = setInterval(() => {
              secondsElapsed++;
              let remaining = MAX_RECORDING_SECONDS - secondsElapsed;
              btnText.innerHTML = `<i class="fas fa-stop"></i> Stop Recording (${remaining}s)`;

              if (secondsElapsed >= MAX_RECORDING_SECONDS) {
                  clearInterval(recordingTimer);
                  recordBtnn.click(); 
                  Swal.fire({
                      title: 'Time Limit Reached',
                      text: 'Recording auto-stopped at 60 seconds.',
                      icon: 'info',
                      timer: 3000,
                      showConfirmButton: false
                  });
              }
          }, 1000);

      } else {
          recording = false;
          clearInterval(recordingTimer); 
          
          recordBtnn.disabled = true;
          btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
          recordBtnn.classList.replace("bg-red-600", "bg-indigo-500");
          recordBtnn.classList.replace("hover:bg-red-700", "hover:bg-indigo-600");
          
          const blob = await stopRecording();
          if(blob) {
              if (blob.size > 10 * 1024 * 1024) {
                  Swal.fire('File too large', 'Your recording is too large. Please try a shorter attempt.', 'error');
                  resetUI();
                  return;
              }
              await sendAudio(blob);
          }
      }
  };

 async function sendAudio(blob) {
    const fd = new FormData();
    fd.append("audio", blob, "speech.webm");
    fd.append("expected_text", sentenceEl.textContent);
    fd.append("mode", analysisMode);

    // Get the CSRF token strictly from the Meta tag (Standard Laravel practice)
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        const res = await fetch("/api/analyze-audio", { // Match your route
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json' // Crucial: forces Laravel to return JSON errors
            },
            body: fd
        });

        // 1. Handle Validation/Credit Errors from Laravel
        if (!res.ok) {
            const errorData = await res.json();
            throw new Error(errorData.details || errorData.error || "Server error");
        }

        const initialData = await res.json();

       let finalData;
if (initialData.task_id) {
    let requiredCost = (analysisMode === 'deep') ? 3 : 2;
    
    // CRITICAL FIX: Pass 'initialData' as the 3rd argument here
    finalData = await handleTaskProcessing(
        initialData.task_id, 
        requiredCost, 
        initialData, // Now handleTaskProcessing can see new_balance and queue
        "Analyzing pronunciation..."
    );
} else {
            finalData = initialData;
        }

          
          
          window.lastSpokenText = finalData.spoken_text || "";
          window.lastAudioUid = finalData.uid; 

          results.classList.remove("hidden");
          
          // Slight delay to ensure DOM update before scrolling smoothly
          setTimeout(() => {
             results.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }, 100);

          const audioUrl = URL.createObjectURL(blob);
          wave.load(audioUrl);

          wave.once('decode', () => {
              regionsPlugin.clearRegions();
              const regionsData = finalData.problem_regions || []; 
              const totalDuration = wave.getDuration();

              regionsData.forEach(r => {
                  const regionDuration = r.end - r.start;
                  if (r.type === 'unclear' && regionDuration > (totalDuration * 0.85)) return; 

                  regionsPlugin.addRegion({
                      start: r.start,
                      end: r.end,
                      color: 'rgba(239, 68, 68, 0.4)',
                      drag: false, resize: false,
                      content: r.type === 'hesitation' ? 'Hesitation' : 'Unclear'
                  });
              });
          });

          document.getElementById("accuracy").textContent      = (finalData.accuracy || 0) + "%";
          document.getElementById("semantic").textContent      = (finalData.semantic_accuracy || 0) + "%";
          document.getElementById("textAcc").textContent       = (finalData.text_accuracy || 0) + "%";
          document.getElementById("grammar").textContent       = (finalData.grammar || 0) + "%";
          document.getElementById("fluency").textContent       = (finalData.fluency || 0) + "%";
          document.getElementById("pronunciation").textContent = (finalData.pronunciation_clarity || 0) + "%";
          document.getElementById("hesitation").textContent    = (finalData.hesitation || 0) + "%";

          document.getElementById("aiTipsBtn").classList.remove("hidden");

      } catch (err) {
          console.error("Analysis Pipeline Error:", err);
          Swal.fire("Analysis Failed", err.message, "error");
      } finally {
          recordBtnn.disabled = false;
          recordBtnn.className = "w-full sm:w-auto px-8 sm:px-14 py-4 sm:py-5 rounded-full bg-slate-900 hover:bg-indigo-600 text-white font-extrabold text-lg sm:text-xl transition-all active:scale-95 shadow-lg sm:shadow-xl shadow-slate-300";
          btnText.innerHTML = '<i class="fas fa-microphone"></i> Record New Attempt';
      }
  }

  /* =========================================================
     5. AI FEEDBACK (LLM)
  ========================================================= */
  const renderAiFeedback = (data) => {
      const aiTipsBox = document.getElementById("aiTips");
      const accent = data.accent_analysis || {};
      const confidencePercent = accent.confidence_score ? Math.round(parseFloat(accent.confidence_score) * 100) : 0;

      // Fully responsive template
      aiTipsBox.innerHTML = `
          <div class="space-y-4 sm:space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700 mt-6 sm:mt-10">
              
              <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                  <div class="lg:col-span-3 bg-white p-5 sm:p-6 rounded-[2rem] border border-indigo-50 shadow-sm flex flex-col justify-center">
                      <div class="flex items-center gap-2 mb-3">
                          <span class="px-2.5 py-1 bg-indigo-100 text-indigo-700 text-[10px] sm:text-xs font-bold rounded uppercase tracking-wider">AI Summary</span>
                      </div>
                      <p class="text-slate-600 text-sm sm:text-base leading-relaxed">${data.summary || 'Analysis complete.'}</p>
                  </div>
                  
                  <div class="bg-gradient-to-br from-indigo-600 to-purple-700 p-6 rounded-[2rem] shadow-lg text-white text-center flex flex-col justify-center">
                      <div class="text-[10px] sm:text-xs uppercase tracking-widest opacity-80 mb-2 font-bold">IELTS Band Hint</div>
                      <div class="text-4xl sm:text-5xl font-black">${data.ielts_band_hint || 'N/A'}</div>
                  </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 items-stretch">
                  
                  <div class="bg-white p-5 sm:p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col h-full">
                      <h4 class="font-bold text-slate-800 mb-5 flex items-center gap-2 text-base sm:text-lg">
                          <i class="fa-solid fa-earth-americas text-indigo-500"></i> Accent Profile
                      </h4>
                      
                      <div class="space-y-4 mb-6">
                          <div>
                              <div class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Detected Style</div>
                              <div class="text-indigo-700 font-extrabold text-xl sm:text-2xl">${accent.detected_style || 'Neutral'}</div>
                          </div>
                          <div>
                              <div class="flex justify-between items-center mb-1.5">
                                  <span class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Confidence</span>
                                  <span class="text-slate-800 font-bold text-xs">${confidencePercent}%</span>
                              </div>
                              <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                  <div class="bg-indigo-500 h-full transition-all duration-1000" style="width: ${confidencePercent}%"></div>
                              </div>
                          </div>
                          <div class="text-xs sm:text-[13px] text-slate-500 italic">Consistency: ${accent.consistency_rating || 'N/A'}</div>
                      </div>

                      <div class="mt-auto bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-100">
                          <div class="text-[10px] text-slate-400 uppercase tracking-wider font-bold mb-1.5">Key Markers</div>
                          <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">${accent.key_markers || 'No markers detected.'}</p>
                      </div>
                  </div>

                  <div class="bg-white p-5 sm:p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col h-full">
                      <h4 class="font-bold text-slate-800 mb-5 flex items-center gap-2 text-base sm:text-lg">
                          <i class="fa-solid fa-triangle-exclamation text-rose-500"></i> Key Issues
                      </h4>
                      <div class="space-y-3 sm:space-y-4 flex-1">
                          ${data.issues && data.issues.length > 0 ? data.issues.map(i => `
                              <div class="bg-rose-50/50 p-4 rounded-2xl border border-rose-100">
                                  <div class="text-rose-700 font-bold text-[10px] uppercase tracking-wider mb-1">${i.type}</div>
                                  <div class="text-slate-800 font-bold text-sm sm:text-base mb-2 leading-tight">${i.problem}</div>
                                  <div class="text-xs sm:text-sm text-slate-600 bg-white/80 p-3 rounded-xl border border-rose-50">
                                      <span class="font-bold text-rose-600"><i class="fas fa-lightbulb"></i> Fix:</span> ${i.how_to_fix}
                                  </div>
                              </div>
                          `).join('') : '<div class="text-slate-400 text-sm italic text-center py-10">Perfect pronunciation!</div>'}
                      </div>
                  </div>

                  <div class="bg-white p-5 sm:p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col h-full md:col-span-2">
                      <h4 class="font-bold text-slate-800 mb-5 flex items-center gap-2 text-base sm:text-lg">
                          <i class="fa-solid fa-bullseye text-blue-500"></i> Word Focus
                      </h4>
                      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                          ${data.word_focus && data.word_focus.length > 0 ? data.word_focus.map(w => `
                              <div class="bg-indigo-50/50 p-4 sm:p-5 rounded-2xl border border-indigo-100 flex flex-col gap-2">
                                  <div class="flex flex-wrap gap-2 justify-between items-center mb-1">
                                      <span class="font-extrabold text-indigo-700 text-base sm:text-lg">${w.word}</span>
                                      <span class="text-[10px] sm:text-xs font-mono bg-white px-2 py-1 rounded border border-indigo-200 text-indigo-500 shadow-sm">/${w.ipa || ''}/</span>
                                  </div>
                                  <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">${w.tip}</p>
                              </div>
                          `).join('') : '<div class="text-slate-400 text-sm italic text-center py-4 col-span-full">No specific words to focus on.</div>'}
                      </div>
                  </div>
              </div>
          </div>`;
      
      aiTipsBox.classList.remove("hidden");
      
      setTimeout(() => {
          aiTipsBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
  };

  aiBtn.onclick = async () => {
      const currentUid = window.lastAudioUid;

      if (aiFeedbackCache[currentUid]) {
          renderAiFeedback(aiFeedbackCache[currentUid]);
          Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'info',
              title: 'Record new audio to get fresh AI feedback.',
              showConfirmButton: false,
              timer: 3000
          });
          return;
      }

      aiBtn.disabled = true;
      aiBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Initializing Gemini...';

      try {
    const startRes = await fetch("/api/pronunciation-tips", { // 1. Added leading slash
        method: "POST",
        headers: { 
            "Content-Type": "application/json",
            "Accept": "application/json", // 2. Forces JSON response for errors
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken
        },
        body: JSON.stringify({
            uid: currentUid,
            expected_text: sentenceEl.textContent.trim(),
            spoken_text: window.lastSpokenText || "",
            scores: {
                // 3. Strip the '%' so the AI gets clean numbers
                pronunciation: (document.getElementById("pronunciation")?.textContent || "0").replace('%', ''),
                fluency: (document.getElementById("fluency")?.textContent || "0").replace('%', ''),
                hesitation: (document.getElementById("hesitation")?.textContent || "0").replace('%', '')
            }
        })
    });

          const startData = await startRes.json();
          if (!startRes.ok) throw new Error(startData.error || "Failed to start AI analysis");

          // Assumes handleTaskProcessing is available
          const finalResult = await handleTaskProcessing(
              startData.task_id, 
              0, 
              "Gemini is listening to your audio...", 
              false 
          );

          if (finalResult && !finalResult.error) {
              aiFeedbackCache[currentUid] = finalResult;
          }

          renderAiFeedback(finalResult);

      } catch (err) {
          console.error("AI Error:", err);
          Swal.fire({ title: 'AI Error', text: err.message, icon: 'error' });
      } finally {
          aiBtn.disabled = false;
          aiBtn.innerHTML = '🤖 Get Advanced AI Pronunciation Feedback';
      }
  };

  /* =========================================================
     6. RESET LOGIC
  ========================================================= */
  const resetAllBtn = document.getElementById("resetAllBtn");

  resetAllBtn.onclick = () => {
      results.classList.add("hidden");
      aiTipsBox.classList.add("hidden");
      aiBtn.classList.add("hidden");

      regionsPlugin.clearRegions();
      wave.empty(); 

      document.getElementById("accuracy").textContent      = "0%";
      document.getElementById("semantic").textContent      = "0%";
      document.getElementById("textAcc").textContent       = "0%";
      document.getElementById("grammar").textContent       = "0%";
      document.getElementById("fluency").textContent       = "0%";
      document.getElementById("pronunciation").textContent = "0%";
      document.getElementById("hesitation").textContent    = "0%";

      resetUI();

      window.scrollTo({ top: 0, behavior: 'smooth' });
  };
</script>