


<div class="mb-6 p-4 md:p-5 bg-white border border-slate-200 rounded-3xl shadow-sm">
    <div class="flex items-end justify-between mb-3">
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex p-2 bg-indigo-50 rounded-xl">
                <i data-lucide="download-cloud" class="w-4 h-4 text-indigo-600"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-slate-800 leading-tight">Downloads</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">
                    Reset: {{ now()->endOfMonth()->format('d M') }}
                </p>
            </div>
        </div>
        
        <div class="text-right">
            <span id="remaining-count" class="text-lg font-black text-slate-800 leading-none">{{ $remaining }}</span>
            <span class="text-[10px] font-bold text-slate-400 uppercase ml-1">Left</span>
        </div>
    </div>


    <div class="relative w-full h-2 bg-slate-100 rounded-full overflow-hidden">
        <div class="absolute top-0 left-0 h-full {{ $barColor }} transition-all duration-700" 
             style="width: {{ $percentage }}%"></div>
    </div>
    
    @if($plan_type === 'free')
        <div class="mt-3 pt-3 border-t border-slate-50 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[10px] font-bold text-slate-500">
                <span class="text-indigo-600">Free Tier:</span> 4 monthly exports
            </p>
            <a href="/upgrade" class="text-[10px] font-black text-indigo-600 uppercase hover:text-indigo-700 flex items-center gap-1">
                Upgrade <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>
    @endif
</div>

<div id="batchActions" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 transition-all duration-500 transform translate-y-32 opacity-0">
    <div class="bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-6 border border-slate-700/50 backdrop-blur-md">
        <div class="flex items-center gap-3 pr-6 border-r border-slate-700">
            <span id="selectedCount" class="bg-indigo-500 text-white w-7 h-7 rounded-full flex items-center justify-center text-xs font-black">0</span>
            <span class="text-sm font-bold text-slate-300 uppercase tracking-wider">Tests Selected</span>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="unselectAll()" class="text-xs font-bold text-slate-400 hover:text-white transition-colors uppercase">
                Cancel
            </button>
            <button onclick="deleteSelected()" class="flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase transition-all active:scale-95">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                Delete Permanently
            </button>
        </div>
    </div>
</div>
@if($result->count() > 0)

    <table class="min-w-full">
        <thead>
            <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">ID</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Topic & Category</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Difficulty</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Created Time</th>
                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                <th class="px-6 py-4 border-b border-slate-200 w-32">
                    <label for="selectAll" class="flex items-center gap-3 cursor-pointer ">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider select-none">Select All</span>
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)" class="w-5 h-5 rounded-lg border-slate-300 text-indigo-600">
                    </label>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($result as $index => $row)
                @php
                    $test_id = $row->id;
                    $category = $row->test_category;
                    $test_uid = $test_id . '-' . $category;
                    $createdAt = strtotime($row->created_at);
                    $isNew = (time() - $createdAt) < 600;
                    $displayName = ucwords($row->display_name);

                    // Title extraction from JSON
                    if (!empty($row->questions)) {
                        $questionsData = json_decode($row->questions, true);
                        if (is_array($questionsData)) {
                            foreach ($questionsData as $item) {
                                if ($category === 'listening' && isset($item['script_title'])) { $displayName = $item['script_title']; break; }
                                elseif ($category === 'single' && isset($item['passage_title'])) { $displayName = $item['passage_title']; break; }
                            }
                        }
                    }

                    // Badge Logic
                    $config = [
                        'listening' => ['bg' => 'bg-purple-50 text-purple-700', 'icon' => 'headphones', 'label' => 'Listening'],
                        'full'      => ['bg' => 'bg-amber-50 text-amber-700', 'icon' => 'award', 'label' => 'Full Exam'],
                        'single'    => ['bg' => 'bg-indigo-50 text-indigo-700', 'icon' => 'book-open', 'label' => 'Reading'],
                    ];
                    $ui = $config[$category] ?? $config['single'];
                @endphp

                <tr id="row-{{ $test_uid }}" class=" hover:bg-slate-50/80 border-b border-slate-100">
                    <td class="px-6 py-6">
                        <span class="font-mono text-[11px] font-black">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </td>
                    <td class="px-6 py-6">
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[16px] font-bold text-slate-800">{{ $displayName }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $ui['bg'] }}">
                                <i data-lucide="{{ $ui['icon'] }}" class="w-3 h-3 mr-1.5"></i> {{ $ui['label'] }}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-6">
                        <span class="text-sm text-slate-600 font-semibold uppercase">{{ $row->difficulty }}</span>
                    </td>
                    <td class="px-6 py-6">
                        <span class="text-sm text-slate-600 font-bold">{{ date("d M, Y", $createdAt) }}</span>
                    </td>
                   <td class="px-6 py-6">
    <div class="flex items-center justify-end gap-5"> <button onclick="handleDownload({{ $test_id }}, '{{ $category }}')" 
                class="p-3 rounded-xl bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-all group/dl"
                title="Download PDF">
            <i data-lucide="download" class="w-5 h-5 transition-transform group-hover/dl:-translate-y-0.5"></i>
        </button>

        <button onclick="confirmDelete({{ $test_id }}, '{{ $category }}')" 
                class="p-3 rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-all"
                title="Delete Test">
            <i data-lucide="trash-2" class="w-5 h-5"></i>
        </button>

        <button onclick="goToTest({{ $test_id }}, '{{ $category }}')" 
                class="ml-2 px-6 py-3 rounded-xl bg-indigo-600 text-white text-[11px] font-black uppercase tracking-wider hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-200 transition-all active:scale-95">
            RESUME
        </button>
        
    </div>
</td>
                    <td class="px-6 py-6 text-center">
                        <input type="checkbox" class="testCheckbox w-5 h-5 rounded-lg text-indigo-600" value="{{ $test_id }}" data-type="{{ $category }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <div class="p-10 text-center text-slate-500">No tests found.</div>
@endif
    <script>
        // Initialize icons
        lucide.createIcons();

        function showProAlert() {
            Swal.fire({
                title: "Pro Feature",
                text: "Upgrade to Pro to download PDF and Audio materials!",
                icon: "info",
                iconColor: "#d97706",
                showCancelButton: true,
                confirmButtonText: "View Pro Plans",
                confirmButtonColor: "#d97706",
                cancelButtonText: "Maybe Later",
                customClass: {
                    popup: "rounded-xl",
                    confirmButton: "rounded-md px-4 py-2"
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "/upgrade";
                }
            });
        }

// 1. Toggle All Checkboxes
function toggleAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.testCheckbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    updateBatchButtonVisibility();
}

// 2. Monitor individual checkbox changes to show/hide the Delete button
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('testCheckbox')) {
        updateBatchButtonVisibility();
    }
});

function updateBatchButtonVisibility() {
    const selected = document.querySelectorAll('.testCheckbox:checked');
    const batchBar = document.getElementById('batchActions');
    const countSpan = document.getElementById('selectedCount');
    
    if (selected.length > 0) {
        // Update the number and slide the bar UP into view
        countSpan.textContent = selected.length;
        batchBar.classList.remove('translate-y-32', 'opacity-0');
        batchBar.classList.add('translate-y-0', 'opacity-100');
    } else {
        // Slide the bar DOWN out of view
        batchBar.classList.add('translate-y-32', 'opacity-0');
        batchBar.classList.remove('translate-y-0', 'opacity-100');
        
        // Uncheck master checkbox if no items are selected
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
    }
}

// Add a helper to quickly clear selection
// 1. Unselect All Helper
function unselectAll() {
    const checkboxes = document.querySelectorAll('.testCheckbox');
    checkboxes.forEach(cb => cb.checked = false);
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    updateBatchButtonVisibility();
}

// 2. Delete Selected Tests (Batch)
async function deleteSelected() {
    const selected = document.querySelectorAll('.testCheckbox:checked');
    if (selected.length === 0) return;

    const result = await Swal.fire({
        title: `Delete ${selected.length} tests?`,
        text: "This will permanently remove all selected items.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        confirmButtonText: 'Yes, delete all',
        customClass: { popup: 'rounded-2xl' }
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Deleting...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let successCount = 0;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        for (let checkbox of selected) {
            const testId = checkbox.value;
            const category = checkbox.getAttribute('data-type');
            
            const formData = new FormData();
            formData.append('test_id', testId);
            formData.append('category', category);

            try {
                // CHANGED: URL updated to '/delete-test' and added CSRF header
                const response = await fetch('/delete-test', { 
                    method: 'POST', 
                    body: formData,
                    headers: { 'X-CSRF-TOKEN': csrfToken } 
                });
                const data = await response.json();
                
                if (data.success) {
                    successCount++;
                    const row = document.getElementById(`row-${testId}-${category}`);
                    if (row) row.remove();
                }
            } catch (error) {
                console.error("Error deleting item:", testId, error);
            }
        }

        Swal.close();
        updateBatchButtonVisibility();
        
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });

        Toast.fire({
            icon: 'success',
            title: `${successCount} tests deleted successfully`
        });

        if (document.querySelectorAll('.testCheckbox').length === 0) {
            location.reload(); 
        }
    }
}

// 3. Delete Single Test
function confirmDelete(testId, category) {
    Swal.fire({
        title: 'Delete Test?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: 'rounded-xl px-5 py-3 font-bold',
            cancelButton: 'rounded-xl px-5 py-3 font-bold'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('test_id', testId);
            formData.append('category', category);
            
            // CHANGED: Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // CHANGED: URL updated to '/delete-test' and added CSRF header
            fetch('/delete-test', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(`row-${testId}-${category}`);
                    if (row) {
                        row.style.transition = 'all 0.5s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        
                        setTimeout(() => {
                            row.remove();
                            const remainingRows = document.querySelectorAll('tbody tr');
                            if (remainingRows.length === 0) {
                                document.querySelector('table').outerHTML = '<div class="p-10 text-center text-slate-500">No tests found.</div>';
                            }
                        }, 500);
                    }

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({ icon: 'success', title: 'Test deleted successfully' });

                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to delete.',
                        icon: 'error',
                        customClass: { popup: 'rounded-2xl' }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An unexpected error occurred.', 'error');
            });
        }
    });
}
// Function to dismiss the 'New' badge on interaction
window.checkAndShowBadges = function() {
    const badges = document.querySelectorAll('.new-test-badge');
    const seenTests = JSON.parse(localStorage.getItem('seen_tests') || '{}');

    badges.forEach(badge => {
        const uid = badge.getAttribute('data-uid');
        if (!seenTests[uid]) {
            badge.classList.remove('hidden'); // Show the badge
        } else {
            badge.remove(); // Remove if already seen
        }
    });

    // Use a specific class or check to avoid double-binding the hover event
    document.querySelectorAll('tr.group:not(.listener-attached)').forEach(row => {
        row.classList.add('listener-attached');
        row.addEventListener('mouseenter', function() {
            const badge = this.querySelector('.new-test-badge');
            if (badge) {
                const uid = badge.getAttribute('data-uid');
                const currentSeen = JSON.parse(localStorage.getItem('seen_tests') || '{}');
                currentSeen[uid] = true;
                localStorage.setItem('seen_tests', JSON.stringify(currentSeen));

                // Fade out effect
                badge.style.transition = 'all 0.6s ease';
                badge.style.opacity = '0';
                badge.style.transform = 'scale(0.8)';
                setTimeout(() => badge.remove(), 600);
            }
        }, { once: true });
    });
};

// Run on initial load
function goToTest(testId, category) {
    // This creates the URL: /test/listening/637
    if (category === 'single') {
        category = 'reading';
    }
    const targetUrl = `/test/${category}/${testId}`;

    // Directly navigate to the URL
    window.location.href = targetUrl;
}


function handleDownload(testId, category) {
    const remaining = {{ $remaining }};
    const plan = '{{ $plan_type }}';
    const countElement = document.getElementById('remaining-count');
    const currentRemaining = countElement ? parseInt(countElement.innerText) : 0;

    if (currentRemaining <= 0) {
        Swal.fire({
            title: 'Limit Reached!',
            text: 'You have used all your downloads for this month.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Upgrade to Pro',
            confirmButtonColor: '#4f46e5',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '/upgrade';
        });
        return;
    }

    Swal.fire({
        title: 'Generate PDF?',
        text: `This will use 1 of your ${countElement.innerText} remaining downloads.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Download',
        confirmButtonColor: '#4f46e5',
        customClass: { popup: 'rounded-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            // Trigger the actual download
            const form = document.createElement('form');
            form.method = 'POST';
            let routePath = category === 'single' ? 'reading' : 'listening';
            form.action = `/download/${routePath}/${testId}`;
            form.target = '_self'; 

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const inputs = { '_token': csrfToken, 'id': testId };
            
            for (const [name, value] of Object.entries(inputs)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // --- LIVE UI UPDATES ---
            if (countElement) {
                const newRemaining = currentRemaining - 1;
                countElement.innerText = newRemaining;

                const progressBar = document.querySelector('.absolute.top-0.left-0.h-full');
                if (progressBar) {
                    const max = (plan === 'pro') ? 100 : 5; 
                    const used = max - newRemaining;
                    const newPercentage = (used / max) * 100;
                    
                    // 1. Update Width
                    progressBar.style.width = `${newPercentage}%`;

                    // 2. LIVE COLOR UPDATE LOGIC
                    // Remove existing color classes
                    progressBar.classList.remove('bg-indigo-600', 'bg-amber-500', 'bg-rose-500');
                    
                    // Add new color based on usage percentage
                    if (newPercentage > 80) {
                        progressBar.classList.add('bg-rose-500'); // Danger/Full
                    } else if (newPercentage > 60) {
                        progressBar.classList.add('bg-amber-500'); // Warning
                    } else {
                        progressBar.classList.add('bg-indigo-600'); // Safe
                    }
                }
            }

            Swal.fire({
                title: 'Generating PDF...',
                text: 'Your download will start shortly.',
                icon: 'success',
                timer: 3000,
                showConfirmButton: false,
                customClass: { popup: 'rounded-2xl' }
            });
        }
    });
}
    </script>
</body>
