/**
 * The Ordervox mark: an order box with voice waves. Matches the WordPress.org icon.
 */
export default function BrandMark() {
	return (
		<svg
			className="ovx-mark"
			viewBox="0 0 256 256"
			width="48"
			height="48"
			aria-hidden="true"
			focusable="false"
		>
			<rect
				width="256"
				height="256"
				rx="56"
				fill="rgba(255,255,255,0.12)"
			/>
			<g transform="translate(-16 0)">
				<path
					d="M62 104 L118 80 L174 104 L174 170 L118 194 L62 170 Z"
					fill="#fff"
				/>
				<path
					d="M62 104 L118 128 L174 104"
					fill="none"
					stroke="#3B2A8C"
					strokeWidth="8"
					strokeLinejoin="round"
				/>
				<path d="M118 128 L118 194" stroke="#3B2A8C" strokeWidth="8" />
				<path
					d="M90 92 L146 116"
					stroke="#3B2A8C"
					strokeWidth="8"
					strokeLinecap="round"
				/>
				<path
					className="ovx-mark__wave"
					d="M190 112 a22 22 0 0 1 0 44"
					fill="none"
					stroke="#FF7A59"
					strokeWidth="10"
					strokeLinecap="round"
				/>
				<path
					className="ovx-mark__wave ovx-mark__wave--outer"
					d="M204 96 a44 44 0 0 1 0 76"
					fill="none"
					stroke="#FF7A59"
					strokeWidth="10"
					strokeLinecap="round"
				/>
			</g>
		</svg>
	);
}
