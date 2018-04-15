<div style="width:500px;margin:0 auto; border:1px solid #eee">
    <div style="min-height:45px;border-top:5px solid #8ac148">
        <div style="width:115px;min-height:30px; padding: 5px;">
            <img style="border:none" src="{{ asset('logo.png') }}">
        </div>
    </div>
    <div style="margin:0 auto;padding:10px">
        <div style="color:#555;overflow:hidden;line-height:25px;font-size:15px;color:#555">
            <h1 style="margin:10px 0;padding:0;line-height:30px;font-size:15px;color:#8ac148;font-weight:bold">親愛的會員您好：</h1>
            @if (isset($introLines))
                @foreach ($introLines as $line)
                    {{ $line }}
                    <br />
                @endforeach
            @endif
            @if (isset($actionText))
                <a href="{{ $actionUrl }}" style="color:#0e90d2;text-decoration:none" target="_blank">
                    {{ $actionText }}
                </a>
                <br />
            @endif
            @if (isset($outroLines))
                @foreach ($outroLines as $line)
                        {{ $line }}
                        <br />
                @endforeach
            @endif
        </div>
        <div style="width:500px;margin:50px auto 10px 0;line-height:25px;font-size:15px;color:#555">
            TRIPLE營運團隊 敬上
        </div>
    </div>
</div>
