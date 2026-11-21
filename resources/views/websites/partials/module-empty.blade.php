<div class="bg-slate-800/60 rounded-xl p-8 shadow text-center">
    <h3 class="text-lg font-medium text-white">No completed analysis yet</h3>
    <p class="text-sm text-gray-400 mt-2 max-w-xl mx-auto">
        {{ $message ?? 'This module reads results from the latest completed crawl. Start an analysis on the website page first.' }}
    </p>
    <a href="{{ route('websites.show', $website) }}"
       class="inline-block mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">
        Go to website &amp; start analysis
    </a>
</div>
