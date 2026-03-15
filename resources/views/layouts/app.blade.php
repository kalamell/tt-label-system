<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ระบบจัดการ Label & สต๊อก FIFO') — TikTok Label System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'thai': ['Noto Sans Thai', 'sans-serif'] },
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Noto Sans Thai', sans-serif; }</style>
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Sidebar --}}
    <div class="flex">
        <aside class="w-64 bg-slate-900 min-h-screen fixed left-0 top-0 z-30">
            <div class="p-6">
                <h1 class="text-white text-lg font-bold">TikTok Label System</h1>
                <p class="text-slate-400 text-xs mt-1">ระบบจัดการ Label & สต๊อก</p>
            </div>

            <nav class="mt-4 px-3 space-y-1">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('dashboard') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/>
                    </svg>
                    Dashboard
                </a>

                <a href="{{ route('orders.index') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('orders.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    ออเดอร์ / Label
                </a>

                @if(request()->routeIs('orders.*'))
                <div class="ml-4 pl-3 border-l border-slate-700 space-y-0.5">
                    <a href="{{ route('orders.index') }}"
                       class="flex items-center px-3 py-1.5 rounded-lg text-xs {{ request()->routeIs('orders.index') || request()->routeIs('orders.show') || request()->routeIs('orders.print') ? 'text-white font-medium' : 'text-slate-400 hover:text-slate-200' }}">
                        รายการออเดอร์
                    </a>
                    <a href="{{ route('orders.upload.form') }}"
                       class="flex items-center px-3 py-1.5 rounded-lg text-xs {{ request()->routeIs('orders.upload.*') ? 'text-white font-medium' : 'text-slate-400 hover:text-slate-200' }}">
                        Upload PDF
                    </a>
                </div>
                @endif

                <div class="pt-4 pb-2 px-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">คลังสินค้า</p>
                </div>

                <a href="{{ route('products.index') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('products.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    สินค้า
                </a>

                <a href="{{ route('inventory.index') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('inventory.index') || request()->routeIs('inventory.show') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    สต๊อก FIFO
                </a>

                <a href="{{ route('inventory.receive.form') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('inventory.receive.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    รับสินค้าเข้า
                </a>

                <a href="{{ route('inventory.transactions') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('inventory.transactions') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    ประวัติ Transaction
                </a>

                <div class="pt-4 pb-2 px-3">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">รายงาน</p>
                </div>

                <a href="{{ route('reports.daily') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('reports.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    รายงานยอดรายวัน
                </a>

                <a href="{{ route('customers.index') }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm {{ request()->routeIs('customers.*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2h5M12 12a4 4 0 100-8 4 4 0 000 8z"/>
                    </svg>
                    ลูกค้า
                </a>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="ml-64 flex-1 min-h-screen">
            {{-- Top Bar --}}
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h2>
                <div class="text-sm text-gray-500">{{ now()->translatedFormat('l j F Y') }}</div>
            </header>

            {{-- Alerts --}}
            <div class="px-6 pt-4">
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <ul class="list-disc list-inside text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Page Content --}}
            <div class="p-6">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
