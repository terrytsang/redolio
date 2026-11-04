<section class="mt-6 rounded-2xl border border-[#E4E4E7] bg-white p-6">
    <p class="text-xs font-bold uppercase tracking-wide text-[#6D28D9]">Get in touch</p>
    <h3 class="mt-1 text-lg font-bold text-[#0A0B0D]">Request a quote or book a consultation</h3>
    <p class="mt-1 text-sm text-[#71717A]">This form is part of the redesign — visitor submissions become leads in your REDO dashboard.</p>

    <form method="POST" action="{{ route('analyses.leads.store', $run->public_id) }}" class="mt-4 flex flex-col gap-3">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2">
            <input type="text" name="name" required placeholder="Your name"
                   class="rounded-lg border border-[#E4E4E7] px-3.5 py-2.5 text-sm focus:border-[#6D28D9] focus:outline-none">
            <input type="email" name="email" required placeholder="Email address"
                   class="rounded-lg border border-[#E4E4E7] px-3.5 py-2.5 text-sm focus:border-[#6D28D9] focus:outline-none">
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <input type="tel" name="phone" placeholder="Phone (optional)"
                   class="rounded-lg border border-[#E4E4E7] px-3.5 py-2.5 text-sm focus:border-[#6D28D9] focus:outline-none">
            <select name="request_type" class="rounded-lg border border-[#E4E4E7] px-3.5 py-2.5 text-sm focus:border-[#6D28D9] focus:outline-none">
                <option value="quote">Request a quote</option>
                <option value="booking">Book an appointment</option>
                <option value="message">General enquiry</option>
            </select>
        </div>
        <textarea name="message" rows="3" placeholder="Tell us what you need..."
                  class="rounded-lg border border-[#E4E4E7] px-3.5 py-2.5 text-sm focus:border-[#6D28D9] focus:outline-none"></textarea>
        <button type="submit" class="self-start rounded-full bg-[#6D28D9] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#5B21B6]">
            Send request
        </button>
    </form>
</section>
